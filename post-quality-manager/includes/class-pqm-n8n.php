<?php
/**
 * n8n public API client (epic n8n provisioning, entry 1; ADR 0015).
 *
 * Holds the connection an Administrator set on the n8n Workflow page — the
 * instance's base URL and an n8n API key — in its own option, autoload off,
 * never in pqm_settings (the Settings save rebuilds that array from the
 * posted form). The key never leaves the server: no REST reply, no page
 * source, no log. Every call sends the plugin User-Agent (a generic one gets
 * banned by some proxies in front of n8n, spike 56) and never follows a
 * redirect, so the key header cannot travel to another address.
 *
 * probe() tells whether an instance can be provisioned, read-only: it
 * creates, changes and deletes nothing on n8n. provision() (entry 4) sets
 * the whole workflow up through request() and removes again what a failed
 * run created (entry 5); later entries call request().
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PQM_N8n {

    /** Own option (autoload off, deleted on uninstall): base_url, api_key, probe, failed. */
    const OPTION = 'pqm_n8n_api';

    /** The n8n public API, below the instance's base URL. */
    const API_PATH = '/api/v1';

    /** An id no n8n object can carry (n8n ids are alphanumeric): the route probe finds nothing to act on. */
    const PROBE_ID = 'pqm-probe-unknown-id';

    /** The n8n version every call was confirmed on (spike 56). */
    const TESTED_VERSION = '2.42.3';

    /**
     * Own option (autoload off, deleted on uninstall): what provision()
     * created, {workflow_id, credentials: {webhook, anthropic, wordpress?},
     * path, wizard_created, at}. Not in OPTION: save() and forget() rewrite
     * that one whole.
     */
    const SETUP_OPTION = 'pqm_n8n_setup';

    /**
     * Own option (autoload off, deleted on uninstall): what a failed run's
     * rollback could not remove from n8n, {base_url, workflow_id?,
     * credentials: [id, …], at} (entry 5, D1). The next provision() removes
     * it first.
     */
    const LEFTOVERS_OPTION = 'pqm_n8n_leftovers';

    /**
     * The stored connection. `probe` is the last passing probe
     * ({ok, cause, message, at}), null when no key is stored. `failed` is
     * the last failed probe that offers the manual path ({cause, message
     * without the manual-path sentence, at}), null when none (entry 2): the
     * Workflow File section says why its reader is on the manual path.
     *
     * @return array{base_url: string, api_key: string, probe: ?array, failed: ?array}
     */
    public static function connection(): array {
        $stored = get_option( self::OPTION, [] );

        return array_merge(
            [ 'base_url' => '', 'api_key' => '', 'probe' => null, 'failed' => null ],
            is_array( $stored ) ? $stored : []
        );
    }

    /**
     * What a REST reply may carry: never the key itself.
     *
     * @return array{base_url: string, key_set: bool, probe: ?array}
     */
    public static function public_state(): array {
        $connection = self::connection();

        return [
            'base_url' => $connection['base_url'],
            'key_set'  => '' !== $connection['api_key'],
            'probe'    => $connection['probe'],
        ];
    }

    /** Store a connection whose probe passed; a recorded failure goes. */
    public static function save( string $base_url, string $api_key, array $probe ): void {
        update_option( self::OPTION, [ 'base_url' => $base_url, 'api_key' => $api_key, 'probe' => $probe ], false );
    }

    /** Forget the key, its probe and a recorded failure; the base URL stays. */
    public static function forget(): void {
        update_option( self::OPTION, [ 'base_url' => self::connection()['base_url'] ], false );
    }

    /**
     * Keep a failed probe's cause for the Workflow File section (entry 2,
     * D1): only the causes that offer the manual path — the results
     * failure() builds through manual(), the one place that sets `reason` —
     * and only `failed` changes, so the stored URL, key and last passing
     * probe stay as they were. The message is the cause sentence without
     * the manual-path one.
     */
    public static function record_failure( array $probe ): void {
        if ( ! empty( $probe['ok'] ) || ! isset( $probe['reason'] ) ) {
            return;
        }
        $stored = get_option( self::OPTION, [] );
        $stored = is_array( $stored ) ? $stored : [];

        $stored['failed'] = [ 'cause' => $probe['cause'], 'message' => $probe['reason'], 'at' => (int) $probe['at'] ];
        update_option( self::OPTION, $stored, false );
    }

    public static function user_agent(): string {
        return 'PostQualityManager/' . PQM_VERSION . ' (+https://postquality.com)';
    }

    /**
     * The instance's base URL: the origin (scheme, host, port) of what the
     * owner pasted, so an editor path like /home/workflows is dropped. HTTPS,
     * or http on localhost / 127.0.0.1 / ::1 only.
     * Skipped: n8n served under a sub-path (N8N_PATH) — add when an owner needs it.
     *
     * @return string|WP_Error The origin, or why the address is refused ('insecure' or 'invalid_url').
     */
    public static function origin( string $url ): string|WP_Error {
        $parts  = wp_parse_url( trim( $url ) ) ?: [];
        $scheme = strtolower( (string) ( $parts['scheme'] ?? '' ) );
        $host   = strtolower( (string) ( $parts['host'] ?? '' ) );

        if ( ! in_array( $scheme, [ 'http', 'https' ], true ) || '' === $host ) {
            return new WP_Error( 'invalid_url', __( 'Enter the full n8n address, for example https://n8n.example.com.', 'post-quality-manager' ) );
        }
        if ( 'https' !== $scheme && ! in_array( trim( $host, '[]' ), [ 'localhost', '127.0.0.1', '::1' ], true ) ) {
            return new WP_Error( 'insecure', __( 'The n8n address must start with https:// (http:// works for localhost only). Nothing was sent.', 'post-quality-manager' ) );
        }

        return $scheme . '://' . $host . ( isset( $parts['port'] ) ? ':' . (int) $parts['port'] : '' );
    }

    /**
     * One call to the stored instance's public API, e.g.
     * request( 'GET', '/workflows?limit=1' ).
     *
     * @param mixed $body JSON-encoded when not null.
     * @return array{code: int, json: ?array}|WP_Error
     */
    public static function request( string $method, string $path, $body = null ): array|WP_Error {
        $connection = self::connection();
        if ( '' === $connection['api_key'] ) {
            return self::not_connected();
        }

        return self::call( $connection['base_url'], $connection['api_key'], $method, self::API_PATH . $path, $body );
    }

    private static function not_connected(): WP_Error {
        return new WP_Error( 'pqm_n8n_not_connected', __( 'No n8n API key is set. Connect your n8n instance on the n8n Workflow page.', 'post-quality-manager' ), [ 'status' => 400 ] );
    }

    /**
     * The one place an HTTP call leaves for n8n. `$key` '' sends no key header.
     * `json` is the decoded body when it is a JSON object or list, else null.
     *
     * @return array{code: int, json: ?array}|WP_Error
     */
    private static function call( string $origin, string $key, string $method, string $path, $body = null ): array|WP_Error {
        $headers = [ 'Accept' => 'application/json' ];
        if ( '' !== $key ) {
            $headers['X-N8N-API-KEY'] = $key;
        }
        $args = [
            'method'      => $method,
            'headers'     => $headers,
            'user-agent'  => self::user_agent(),
            'timeout'     => 10,
            'redirection' => 0,
        ];
        if ( null !== $body ) {
            $args['headers']['Content-Type'] = 'application/json';
            $args['body']                    = wp_json_encode( $body );
        }

        $response = wp_remote_request( $origin . $path, $args );
        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $json = json_decode( (string) wp_remote_retrieve_body( $response ), true );

        return [
            'code' => (int) wp_remote_retrieve_response_code( $response ),
            'json' => is_array( $json ) ? $json : null,
        ];
    }

    /**
     * Can this instance be provisioned with this key? In order: the URL rule
     * (no call when refused), the key on a one-item workflow list, then the
     * endpoints provisioning needs, probed with an id that cannot exist
     * (decision D2). No version is read: n8n 2.42.3 exposes none to an API
     * key (owner probe 2026-10-13: /rest/settings carries no version before
     * login), so the endpoint probe is the version check.
     *
     * @return array{ok: bool, cause: string, message: string, at: int, reason?: string}
     *         `cause`: '' when ok, else insecure, invalid_url, key, api_disabled,
     *         proxy, unreachable, missing_endpoint or unexpected. A failure that
     *         offers the manual path also carries `reason`, the message
     *         without the manual-path sentence (record_failure() keeps it).
     */
    public static function probe( string $url, string $key ): array {
        $origin = self::origin( $url );
        if ( is_wp_error( $origin ) ) {
            return self::result( $origin->get_error_code(), $origin->get_error_message() );
        }

        $list = self::call( $origin, $key, 'GET', self::API_PATH . '/workflows?limit=1' );
        if ( ! is_wp_error( $list ) && $list['code'] >= 200 && $list['code'] < 300 && null !== $list['json'] ) {
            // The key works; does this version have every endpoint the setup needs?
            // An empty JSON body: without one, PATCH answers 415 before the route is known.
            foreach ( [ [ 'POST', '/workflows/' . self::PROBE_ID . '/activate' ], [ 'PATCH', '/credentials/' . self::PROBE_ID ] ] as [ $method, $path ] ) {
                $route = self::call( $origin, $key, $method, self::API_PATH . $path, (object) [] );
                if ( is_wp_error( $route ) || ! self::route_exists( $route ) ) {
                    return self::failure( $route, true );
                }
            }

            return [ 'ok' => true, 'cause' => '', 'message' => __( 'Connected to n8n.', 'post-quality-manager' ), 'at' => time() ];
        }

        return self::failure( $list, false );
    }

    /**
     * Decision D2, as n8n 2.42.3 answers (owner probe 2026-10-13): a missing
     * route gets the router's generic `{"message":"not found"}`; an existing
     * one reaches its handler, which refuses the unknown id with its own
     * message ("Credential not found", "You do not have permission to
     * activate this workflow…"), or its validator (400, 415).
     */
    private static function route_exists( array $response ): bool {
        $message = $response['json']['message'] ?? null;

        return in_array( $response['code'], [ 400, 404, 415 ], true ) && is_string( $message ) && 'not found' !== strtolower( trim( $message ) );
    }

    /**
     * The cause of an answer that is not the expected one, each pointing to
     * the manual path. `$on_route`: the answer came from the route probe,
     * where a 405, or a 404 that is the router's generic "not found" (or a
     * JSON 404 without a message), means the endpoint is missing; a non-JSON
     * answer is never read as a missing endpoint. Each cause sentence is
     * built once: `message` appends the manual-path sentence, `reason`
     * carries it bare for record_failure().
     *
     * @param array|WP_Error $response
     */
    private static function failure( $response, bool $on_route ): array {
        if ( is_wp_error( $response ) ) {
            return self::manual( 'unreachable', sprintf(
                /* translators: %s: connection error message */
                __( 'Could not reach n8n: %s. Check the address and that n8n is running.', 'post-quality-manager' ),
                $response->get_error_message()
            ) );
        }

        $code = $response['code'];

        if ( $on_route && ( 405 === $code || ( 404 === $code && null !== $response['json'] ) ) ) {
            return self::manual( 'missing_endpoint', sprintf(
                /* translators: %s: tested n8n version, e.g. "2.42.3" */
                __( 'Your n8n lacks an API endpoint the setup needs. Update n8n to %s or later.', 'post-quality-manager' ),
                self::TESTED_VERSION
            ) );
        }
        if ( ! $on_route && 401 === $code ) {
            return self::result( 'key', __( 'n8n refused this API key. Create a new one in n8n under Settings → n8n API, then try again.', 'post-quality-manager' ) );
        }
        if ( ! $on_route && 404 === $code ) {
            return self::manual( 'api_disabled', __( 'n8n answered 404: the public API is turned off on this instance (N8N_PUBLIC_API_DISABLED), or this is not your n8n address. Check the address.', 'post-quality-manager' ) );
        }
        if ( 403 === $code || null === $response['json'] ) {
            return self::manual( 'proxy', __( 'n8n did not answer as expected: a proxy or firewall in front of it is the likely cause. Allow calls from this site to the /api/v1/ path of n8n.', 'post-quality-manager' ) );
        }

        return self::manual( 'unexpected', sprintf(
            /* translators: %d: HTTP status code */
            __( 'n8n answered HTTP %d. Try again in a moment.', 'post-quality-manager' ),
            $code
        ) );
    }

    /** A failed result whose message offers the manual path; `reason` is the bare cause sentence. */
    private static function manual( string $cause, string $sentence ): array {
        $message = $sentence . ' ' . __( 'Or set up the workflow by hand with the Workflow File section.', 'post-quality-manager' );

        return self::result( $cause, $message ) + [ 'reason' => $sentence ];
    }

    private static function result( string $cause, string $message ): array {
        return [ 'ok' => false, 'cause' => $cause, 'message' => $message, 'at' => time() ];
    }

    /* ─── Provisioning (entry 4) ─────────────────────────────────────── */

    /**
     * Set this site's workflow up on the connected instance, in order: check
     * the Anthropic key with Anthropic itself (n8n cannot test a generic
     * credential, spike 56); on the Pro build, create an Application Password
     * for the current user; create the credentials under the names the
     * template's nodes carry; create the build's workflow with every
     * credentialed node linked by id; activate it; store the setup map.
     * The provider key goes into the validation request and the Anthropic
     * credential only — never an option, a reply or a log. A refused step
     * after the first creation stops the run and goes through fail(): what
     * this run created is removed again (entry 5). What an earlier rollback
     * could not remove (LEFTOVERS_OPTION) is removed before anything is
     * created. An existing setup map refuses the run: nothing is adopted or
     * updated.
     *
     * @return array|WP_Error The stored setup map (SETUP_OPTION).
     */
    public static function provision( string $provider_key ): array|WP_Error {
        if ( false !== get_option( self::SETUP_OPTION, false ) ) {
            return new WP_Error( 'pqm_already_provisioned', __( 'n8n is already set up for this site.', 'post-quality-manager' ), [ 'status' => 409 ] );
        }
        if ( '' === self::connection()['api_key'] ) {
            return self::not_connected();
        }
        // Objects, not arrays: an empty `{}` in the template must stay one.
        $workflow = json_decode( (string) pqm_workflow_json() );
        if ( ! is_object( $workflow ) ) {
            return new WP_Error( 'pqm_provision_failed', __( 'The workflow template is missing from the plugin. Reinstall the plugin, then try again.', 'post-quality-manager' ), [ 'status' => 500, 'step' => 'template' ] );
        }

        $checked = self::check_provider_key( $provider_key );
        if ( is_wp_error( $checked ) ) {
            return $checked;
        }
        $blocked = self::remove_leftovers();
        if ( is_wp_error( $blocked ) ) {
            return $blocked;
        }

        // The deterministic names: the template's nodes, the setup dialog and the Workflow File section carry them too.
        $host        = pqm_site_host();
        $credentials = [
            'webhook'   => [ 'name' => 'Post Quality webhook - ' . $host, 'type' => 'httpHeaderAuth', 'data' => [ 'name' => 'X-PQM-Token', 'value' => (string) pqm_get_settings()['webhook_token'] ] ],
            'anthropic' => [ 'name' => 'Anthropic - Claude', 'type' => 'httpHeaderAuth', 'data' => [ 'name' => 'x-api-key', 'value' => $provider_key ] ],
        ];
        if ( pqm_fs()->is__premium_only() ) {
            $wordpress = self::wordpress_credential__premium_only( $host );
            if ( is_wp_error( $wordpress ) ) {
                return $wordpress;
            }
            $credentials['wordpress'] = $wordpress;
        }

        $ids     = []; // credential name => id; null = deliberately left name-only (D1)
        $created = []; // role => id, for the setup map
        foreach ( $credentials as $role => $credential ) {
            if ( null === $credential['data'] ) {
                $ids[ $credential['name'] ] = null;
                continue;
            }
            // Name, type and data only: the Pro build's WordPress credential also carries a uuid for the revoke.
            $response = self::request( 'POST', '/credentials', [ 'name' => $credential['name'], 'type' => $credential['type'], 'data' => $credential['data'] ] );
            $id       = self::created_id( $response );
            if ( null === $id ) {
                return self::fail( 'credential_' . $role, self::stopped( 'credential_' . $role, $response ), $credentials, $created );
            }
            $ids[ $credential['name'] ] = $id;
            $created[ $role ]           = $id;
        }

        // Link by credential name: both builds carry two httpHeaderAuth credentials.
        foreach ( $workflow->nodes as $node ) {
            foreach ( (array) ( $node->credentials ?? [] ) as $type => $ref ) {
                $name = (string) ( $ref->name ?? '' );
                if ( ! array_key_exists( $name, $ids ) ) {
                    return self::fail( 'link', sprintf(
                        /* translators: %s: credential name from the workflow template */
                        __( 'The workflow template names a credential the setup does not create (%s).', 'post-quality-manager' ),
                        $name
                    ), $credentials, $created );
                }
                if ( null !== $ids[ $name ] ) {
                    $node->credentials->{$type} = [ 'id' => $ids[ $name ], 'name' => $name ];
                }
            }
        }

        // No `active`: the public API sets it through activate only.
        $response    = self::request( 'POST', '/workflows', [
            'name'        => $workflow->name,
            'nodes'       => $workflow->nodes,
            'connections' => $workflow->connections,
            'settings'    => $workflow->settings,
        ] );
        $workflow_id = self::created_id( $response );
        if ( null === $workflow_id ) {
            return self::fail( 'workflow', self::stopped( 'workflow', $response ), $credentials, $created );
        }

        $response = self::request( 'POST', '/workflows/' . rawurlencode( $workflow_id ) . '/activate', (object) [] );
        if ( is_wp_error( $response ) || $response['code'] < 200 || $response['code'] >= 300 ) {
            return self::fail( 'activate', self::stopped( 'activate', $response ), $credentials, $created, $workflow_id );
        }

        $setup = [
            'workflow_id'    => $workflow_id,
            'credentials'    => $created,
            'path'           => pqm_webhook_path(),
            'wizard_created' => true,
            'at'             => time(),
        ];
        update_option( self::SETUP_OPTION, $setup, false );

        return $setup;
    }

    /**
     * Ask Anthropic whether it accepts the key: the cheapest authenticated
     * call, raw (the plugin ships no SDK). Null when it does. 401/403 is a
     * refused key; anything else (no answer, 5xx, 429) means the key could
     * not be checked. Neither error carries the key.
     */
    private static function check_provider_key( string $key ): ?WP_Error {
        if ( '' === $key ) {
            return new WP_Error( 'pqm_provider_key_invalid', __( 'Enter your Anthropic API key. Create one in the Anthropic Console under API Keys.', 'post-quality-manager' ), [ 'status' => 400 ] );
        }

        $response = wp_remote_get( 'https://api.anthropic.com/v1/models?limit=1', [
            'headers'     => [ 'x-api-key' => $key, 'anthropic-version' => '2023-06-01' ],
            'user-agent'  => self::user_agent(),
            'timeout'     => 10,
            'redirection' => 0,
        ] );
        $code = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );

        if ( $code >= 200 && $code < 300 ) {
            return null;
        }
        if ( 401 === $code || 403 === $code ) {
            return new WP_Error( 'pqm_provider_key_invalid', __( 'Anthropic refused this API key. Copy it again from the Anthropic Console under API Keys. Nothing was created in n8n.', 'post-quality-manager' ), [ 'status' => 400 ] );
        }

        return new WP_Error( 'pqm_provider_unreachable', sprintf(
            /* translators: %s: what went wrong, e.g. "HTTP 529" or a connection error */
            __( 'Could not check the Anthropic API key (%s). Try again in a moment. Nothing was created in n8n.', 'post-quality-manager' ),
            is_wp_error( $response ) ? $response->get_error_message() : 'HTTP ' . $code
        ), [ 'status' => 502 ] );
    }

    /**
     * The Pro build's WordPress credential, for the node that saves a
     * rewrite: a new Application Password of the current user. `data` is
     * null when the site offers no Application Passwords (D1): the credential
     * is not created and its node stays name-only. `uuid` names the created
     * Application Password for the revoke of a failed run; it never goes to
     * n8n.
     */
    private static function wordpress_credential__premium_only( string $host ): array|WP_Error {
        $credential = [ 'name' => 'WordPress - ' . $host, 'type' => 'httpBasicAuth', 'data' => null ];
        $user       = wp_get_current_user();
        if ( ! wp_is_application_passwords_available_for_user( $user ) ) {
            return $credential;
        }

        $created = WP_Application_Passwords::create_new_application_password( $user->ID, [ 'name' => 'Post Quality Manager (n8n) ' . gmdate( 'Y-m-d H:i:s' ) ] );
        if ( is_wp_error( $created ) ) {
            return new WP_Error( 'pqm_provision_failed', sprintf(
                /* translators: %s: why WordPress refused, e.g. "Each application name should be unique." */
                __( 'Could not create the Application Password for n8n: %s Nothing was created in n8n.', 'post-quality-manager' ),
                $created->get_error_message()
            ), [ 'status' => 500, 'step' => 'application_password' ] );
        }
        $credential['data'] = [ 'user' => $user->user_login, 'password' => $created[0] ];
        $credential['uuid'] = $created[1]['uuid'];

        return $credential;
    }

    /** Revoke the Application Password this run created, if it created one: the run failed, n8n will not use it. */
    private static function revoke_application_password__premium_only( array $credentials ): void {
        if ( isset( $credentials['wordpress']['uuid'] ) ) {
            WP_Application_Passwords::delete_application_password( wp_get_current_user()->ID, $credentials['wordpress']['uuid'] );
        }
    }

    /**
     * The id n8n gave a created object, null when the call did not create one.
     *
     * @param array|WP_Error $response
     */
    private static function created_id( $response ): ?string {
        if ( is_wp_error( $response ) || $response['code'] < 200 || $response['code'] >= 300 ) {
            return null;
        }
        $id = $response['json']['id'] ?? null;

        return is_scalar( $id ) && '' !== (string) $id ? (string) $id : null;
    }

    /**
     * A failed run, after its first creation: revoke the Pro build's
     * Application Password, remove from n8n what this run created, keep
     * what could not be removed (D1), and answer `data.step` with a message
     * that says whether anything was left. 502, or 500 for `link` (the
     * template, not n8n).
     *
     * @param string $cause   The sentence naming what went wrong.
     * @param array  $created role => id of the credentials this run created.
     */
    private static function fail( string $step, string $cause, array $credentials, array $created, ?string $workflow_id = null ): WP_Error {
        // Before n8n's rollback, and whatever it gives: the WordPress credential is useless once the run failed.
        if ( pqm_fs()->is__premium_only() ) {
            self::revoke_application_password__premium_only( $credentials );
        }
        $left = self::roll_back( $workflow_id, array_values( $created ) );
        if ( null !== $left ) {
            update_option( self::LEFTOVERS_OPTION, [ 'base_url' => self::connection()['base_url'] ] + $left + [ 'at' => time() ], false );
        }

        return new WP_Error( 'pqm_provision_failed', implode( ' ', [
            $cause,
            null === $left
                ? __( 'Nothing was left in n8n.', 'post-quality-manager' )
                : __( 'Some of what it created is still in n8n: the next try removes it first.', 'post-quality-manager' ),
            'link' === $step
                ? __( 'Update the plugin, then try again.', 'post-quality-manager' )
                : __( 'Try again in a moment.', 'post-quality-manager' ),
        ] ), [ 'status' => 'link' === $step ? 500 : 502, 'step' => $step ] );
    }

    /**
     * Remove from n8n a workflow and credentials the plugin created, in the
     * forced order (spike 56): deactivate the workflow whatever it answered
     * before, delete it, then the credentials — only once the workflow is
     * gone, since n8n deletes a credential silently under a workflow that
     * uses it. Only the given ids, never a lookup by name. n8n unreachable
     * (a WP_Error) stops the rollback there: every further call would wait
     * the full timeout, so the record is written before a gateway gives up.
     *
     * @param string[] $credentials Credential ids.
     * @return array|null Null when everything is gone, else what remains:
     *                    {workflow_id?, credentials: [id, …]}.
     */
    private static function roll_back( ?string $workflow_id, array $credentials ): ?array {
        $credentials = array_values( $credentials );
        if ( null !== $workflow_id ) {
            $path = '/workflows/' . rawurlencode( $workflow_id );
            // Only reachability matters: deactivating an inactive workflow answers 200, and the delete tells.
            // n8n 2.42.3 refuses the delete with 409 until it has unpublished the workflow: no credential goes before it.
            if ( is_wp_error( self::request( 'POST', $path . '/deactivate', (object) [] ) ) || ! self::removed( self::request( 'DELETE', $path ) ) ) {
                return [ 'workflow_id' => $workflow_id, 'credentials' => $credentials ];
            }
        }
        $left = [];
        foreach ( $credentials as $i => $id ) {
            $response = self::request( 'DELETE', '/credentials/' . rawurlencode( (string) $id ) );
            if ( is_wp_error( $response ) ) {
                // Unreachable: this id and every untried one stay, without a call each.
                $left = array_merge( $left, array_slice( $credentials, $i ) );
                break;
            }
            if ( ! self::removed( $response ) ) {
                $left[] = $id;
            }
        }

        return [] === $left ? null : [ 'credentials' => $left ];
    }

    /**
     * Whether a DELETE left the object gone: 2xx, or a JSON 404 (n8n 2.42.3
     * answers `404 {"message":"Not Found"}` for an id it does not know; a
     * non-JSON 404 comes from a proxy, so the object may still be there).
     *
     * @param array|WP_Error $response
     */
    private static function removed( $response ): bool {
        return ! is_wp_error( $response ) && ( ( 404 === $response['code'] && null !== $response['json'] ) || ( $response['code'] >= 200 && $response['code'] < 300 ) );
    }

    /**
     * Before a run creates anything: remove what an earlier run's rollback
     * left on the connected instance (D1). A record of another instance is
     * dropped; still refused, the record keeps what remains and the run
     * stops with 502 step `cleanup`.
     */
    private static function remove_leftovers(): ?WP_Error {
        $leftovers = get_option( self::LEFTOVERS_OPTION, false );
        if ( ! is_array( $leftovers ) ) {
            return null;
        }
        $base_url = self::connection()['base_url'];
        $left     = ( $leftovers['base_url'] ?? '' ) === $base_url
            ? self::roll_back( $leftovers['workflow_id'] ?? null, (array) ( $leftovers['credentials'] ?? [] ) )
            : null;
        if ( null === $left ) {
            delete_option( self::LEFTOVERS_OPTION );

            return null;
        }
        update_option( self::LEFTOVERS_OPTION, [ 'base_url' => $base_url ] + $left + [ 'at' => time() ], false );

        return new WP_Error( 'pqm_provision_failed', __( 'An earlier n8n setup left a workflow or credentials in n8n that could not be removed yet, so nothing was created. Try again in a moment. If this keeps happening, check that your n8n API key is still valid by connecting again on the n8n Workflow page, or remove the Post Quality workflow and credentials in n8n by hand.', 'post-quality-manager' ), [ 'status' => 502, 'step' => 'cleanup' ] );
    }

    /**
     * Why an n8n step stopped the run, in plain words. n8n's reply body is
     * not repeated (it may quote the request).
     *
     * @param array|WP_Error $response
     */
    private static function stopped( string $step, $response ): string {
        $labels = [
            'credential_webhook'   => __( 'creating the webhook credential', 'post-quality-manager' ),
            'credential_anthropic' => __( 'creating the Anthropic credential', 'post-quality-manager' ),
            'credential_wordpress' => __( 'creating the WordPress credential', 'post-quality-manager' ),
            'workflow'             => __( 'creating the workflow', 'post-quality-manager' ),
            'activate'             => __( 'activating the workflow', 'post-quality-manager' ),
        ];
        if ( is_wp_error( $response ) ) {
            /* translators: %s: connection error message */
            $answer = sprintf( __( 'n8n could not be reached: %s', 'post-quality-manager' ), $response->get_error_message() );
        } elseif ( $response['code'] >= 200 && $response['code'] < 300 ) {
            $answer = __( 'n8n did not return what it created', 'post-quality-manager' );
        } else {
            /* translators: %d: HTTP status code */
            $answer = sprintf( __( 'n8n answered HTTP %d', 'post-quality-manager' ), $response['code'] );
        }

        return sprintf(
            /* translators: 1: setup step, e.g. "creating the webhook credential"; 2: what n8n answered, e.g. "n8n answered HTTP 400" */
            __( 'The n8n setup stopped while %1$s (%2$s).', 'post-quality-manager' ),
            $labels[ $step ] ?? $step,
            $answer
        );
    }
}
