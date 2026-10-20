<?php
/**
 * n8n public API client (epic n8n provisioning, entry 1; ADR 0015).
 *
 * Holds the connection an Administrator set on the n8n Workflow page — the
 * instance's base URL and an n8n API key — in its own option, autoload off,
 * never in pqm_settings (the Settings save rebuilds that array from the
 * posted form). The key never leaves the server: no REST reply, no page
 * source, no log carries it; only its last four characters are shown, so an
 * owner can tell which key is stored (key_hint). Every call sends the
 * plugin User-Agent (a generic one gets banned by some proxies in front of
 * n8n, spike 56) and never follows a redirect, so the key header cannot
 * travel to another address.
 *
 * probe() tells whether an instance can be provisioned, read-only: it
 * creates, changes and deletes nothing on n8n. provision() (entry 4) sets
 * the whole workflow up through request() and removes again what a failed
 * run created (entry 5); diagnose() (entry 7) reads the workflow behind the
 * saved webhook URL, GET only; propose_adoption() and adopt() (entry 8)
 * take over a hand-made workflow by storing its setup map, GET only;
 * rotate_token() (entry 10) PATCHes the map's webhook credential with a new
 * token, its one call; update_workflow() (entry 9) reads the map's workflow
 * once and PUTs the build's template onto it, credentials and path kept.
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

    /** A key shorter than this shows no hint: its last four would give away too large a share. */
    const KEY_HINT_MIN_LENGTH = 16;

    /**
     * Own option (autoload off, deleted on uninstall): the workflow the
     * plugin manages, {workflow_id, credentials: {webhook, anthropic,
     * wordpress?}, path, wizard_created, at} — what provision() created
     * (wizard_created true) or what adopt() took over (false). Not in
     * OPTION: save() and forget() rewrite that one whole.
     */
    const SETUP_OPTION = 'pqm_n8n_setup';

    /** The active workflows, one page of 250 (n8n's maximum `limit`): the diagnosis and the adoption read them. */
    const ACTIVE_WORKFLOWS = '/workflows?active=true&limit=250&excludePinnedData=true';

    /**
     * Own option (autoload off, deleted on uninstall): what a failed run's
     * rollback could not remove from n8n, {base_url, workflow_id?,
     * credentials: [id, …], at} (entry 5, D1). The next provision() removes
     * it first.
     */
    const LEFTOVERS_OPTION = 'pqm_n8n_leftovers';

    /**
     * Own option (autoload off, deleted on uninstall): {at} while a
     * provisioning run is on its way (bug 72, D1): claimed before the first
     * outgoing call, released on every exit, so a second run started during
     * the first is refused as a site already set up. Not a placeholder setup
     * map, which would turn the page "set up" during the run. See
     * memory/n8n-provisioning.md (publication outbox finding, 2026-10-20).
     */
    const LOCK_OPTION = 'pqm_n8n_provisioning';

    /** A lock older than this (seconds) is a run PHP killed mid-way: the next run takes it over. */
    const LOCK_TTL = 300;

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
     * What a REST reply may carry: never the key itself. `key_hint` is the
     * stored key's last four characters, computed here and never stored,
     * empty when no key is stored or it is shorter than KEY_HINT_MIN_LENGTH.
     * `setup` is what the setup map says of itself ({wizard_created, at}),
     * null without one; `adoptable` whether the plugin may take over the
     * site's workflow (entry 8: a passing key, a saved webhook URL, no map);
     * `rotatable` whether it may rotate the webhook token (entry 10: a
     * passing key, a map, the saved URL on the map's path) — the same gate
     * that offers the workflow update (entry 9), managed_blocked().
     *
     * @return array{base_url: string, key_set: bool, key_hint: string, probe: ?array, setup: ?array, adoptable: bool, rotatable: bool}
     */
    public static function public_state(): array {
        $connection = self::connection();
        $key        = (string) $connection['api_key'];
        $setup      = get_option( self::SETUP_OPTION, false );

        return [
            'base_url'  => $connection['base_url'],
            'key_set'   => '' !== $key,
            'key_hint'  => strlen( $key ) >= self::KEY_HINT_MIN_LENGTH ? substr( $key, -4 ) : '',
            'probe'     => $connection['probe'],
            'setup'     => is_array( $setup ) ? [ 'wizard_created' => ! empty( $setup['wizard_created'] ), 'at' => (int) ( $setup['at'] ?? 0 ) ] : null,
            'adoptable' => null === self::adoption_blocked(),
            'rotatable' => null === self::managed_blocked(),
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

    /* ─── Mis-wiring diagnosis (entry 7) ─────────────────────────────── */

    /**
     * Is the workflow behind the saved webhook URL wired to the right
     * credentials? GET only. The workflow is found by the setup map's id
     * while the URL's path is the map's (entry 8, D3: the status block
     * describes what the site calls), else as the active workflow whose
     * Webhook trigger path is the URL's last segment; never by name. Its
     * published nodes are read (n8n 2.x runs `activeVersion`; a save changes
     * only the draft), disabled ones skipped; miswiring() names the shape.
     *
     * @return string|null The sentence naming the nodes, the credential and
     *                     the fix; '' when the workflow is clean or no key is
     *                     stored; null when the check could not run (n8n
     *                     unreachable or refusing, no matching workflow).
     */
    public static function diagnose( string $webhook_url ): ?string {
        if ( '' === self::connection()['api_key'] ) {
            return '';
        }
        $setup = get_option( self::SETUP_OPTION, false );
        $setup = is_array( $setup ) && '' !== (string) ( $setup['workflow_id'] ?? '' )
            && self::webhook_segment( $webhook_url ) === (string) ( $setup['path'] ?? '' ) ? $setup : null;

        if ( null !== $setup ) {
            $response = self::request( 'GET', '/workflows/' . rawurlencode( (string) $setup['workflow_id'] ) );
            $nodes    = self::answered( $response ) ? self::published_nodes( $response['json'] ) : null;
        } else {
            $workflow = self::workflow_at( $webhook_url );
            $nodes    = is_array( $workflow ) ? $workflow['nodes'] : null;
        }
        if ( null === $nodes || null === self::trigger( $nodes ) ) {
            return null; // n8n did not answer, no workflow listens there, or not a workflow read (a 2xx `{}`).
        }

        // D1: a provisioned site without Application Passwords leaves Update WP Post (Rewrite) name-only.
        return self::miswiring( $nodes, null !== $setup && ! isset( $setup['credentials']['wordpress'] ), false );
    }

    /**
     * The wiring the diagnosis reports among these published nodes (their
     * trigger included), as one sentence; '' when clean. Nodes are told
     * apart by type and parameters, never by their names, which the owner
     * may change. Three shapes count, the first found is reported: Claude
     * nodes carrying the trigger's credential (the 2026-10-13 import), then
     * a Claude node or the trigger carrying a credential of another type
     * than Header Auth (n8n's native Anthropic credential, say: story 67,
     * E7), then nodes that need a credential and have none. Node and
     * credential names are n8n's: the sentence is raw text, escaped where
     * it is shown.
     *
     * @param bool $skip_basic The node saving a rewrite (httpBasicAuth) may go without one.
     * @param bool $adopting   The adoption (entry 8) asks: the sentence ends "then try again", not "then test again".
     */
    private static function miswiring( array $nodes, bool $skip_basic, bool $adopting ): string {
        $trigger = self::trigger( $nodes ) ?? [];
        $hook    = (string) ( $trigger['credentials']['httpHeaderAuth']['id'] ?? '' );
        $shared  = [];
        $other   = [];
        $missing = [];
        foreach ( $nodes as $node ) {
            $parameters  = is_array( $node['parameters'] ?? null ) ? $node['parameters'] : [];
            $credentials = is_array( $node['credentials'] ?? null ) ? $node['credentials'] : [];
            $name        = (string) ( $node['name'] ?? '' );

            if ( '' !== $hook && self::is_claude( $node ) && $hook === (string) ( $credentials['httpHeaderAuth']['id'] ?? '' ) ) {
                $shared[] = $name;
            }
            // The trigger and the Claude nodes take Header Auth: any other linked type is the wrong one.
            if ( ( self::is_claude( $node ) || $node === $trigger ) && self::has_credential( $credentials )
                && ! self::has_credential( array_intersect_key( $credentials, [ 'httpHeaderAuth' => true ] ) ) ) {
                $other[] = $name;
            }
            $auth = (string) ( $parameters['authentication'] ?? '' );
            if ( '' !== $auth && 'none' !== $auth && ! self::has_credential( $credentials )
                && ! ( $skip_basic && 'httpBasicAuth' === ( $parameters['genericAuthType'] ?? '' ) ) ) {
                $missing[] = $name;
            }
        }

        if ( [] !== $shared ) {
            $credential = (string) ( $trigger['credentials']['httpHeaderAuth']['name'] ?? '' );
            if ( '' === $credential ) {
                return sprintf(
                    $adopting
                        /* translators: %s: n8n node names, e.g. "Claude - Review Agent and Claude - SEO Agent" */
                        ? __( 'In n8n, the webhook credential is set on %s instead of your Anthropic credential: pick the Anthropic one there, publish the workflow, then try again.', 'post-quality-manager' )
                        /* translators: %s: n8n node names, e.g. "Claude - Review Agent and Claude - SEO Agent" */
                        : __( 'In n8n, the webhook credential is set on %s instead of your Anthropic credential: pick the Anthropic one there, publish the workflow, then test again.', 'post-quality-manager' ),
                    wp_sprintf( '%l', $shared )
                );
            }

            return sprintf(
                $adopting
                    /* translators: 1: the n8n credential's name, e.g. "Header Auth account"; 2: n8n node names, e.g. "Claude - Review Agent and Claude - SEO Agent" */
                    ? __( 'In n8n, the webhook credential “%1$s” is set on %2$s instead of your Anthropic credential: pick the Anthropic one there, publish the workflow, then try again.', 'post-quality-manager' )
                    /* translators: 1: the n8n credential's name, e.g. "Header Auth account"; 2: n8n node names, e.g. "Claude - Review Agent and Claude - SEO Agent" */
                    : __( 'In n8n, the webhook credential “%1$s” is set on %2$s instead of your Anthropic credential: pick the Anthropic one there, publish the workflow, then test again.', 'post-quality-manager' ),
                $credential,
                wp_sprintf( '%l', $shared )
            );
        }
        if ( [] !== $other ) {
            return sprintf(
                $adopting
                    /* translators: %s: n8n node names, e.g. "Claude - Review Agent and Claude - SEO Agent" */
                    ? __( 'In n8n, %s uses a credential of another type than Header Auth: pick the Header Auth credential made for it there (your Anthropic credential in a Claude node, the webhook credential in the Webhook Trigger), publish the workflow, then try again.', 'post-quality-manager' )
                    /* translators: %s: n8n node names, e.g. "Claude - Review Agent and Claude - SEO Agent" */
                    : __( 'In n8n, %s uses a credential of another type than Header Auth: pick the Header Auth credential made for it there (your Anthropic credential in a Claude node, the webhook credential in the Webhook Trigger), publish the workflow, then test again.', 'post-quality-manager' ),
                wp_sprintf( '%l', $other )
            );
        }
        if ( [] !== $missing ) {
            return self::no_credential( $missing, $adopting );
        }

        return '';
    }

    /** The sentence for nodes that need a credential and have none; `$adopting` as in miswiring(). */
    private static function no_credential( array $names, bool $adopting ): string {
        return sprintf(
            $adopting
                /* translators: %s: n8n node names, e.g. "Update WP Post (Rewrite)" */
                ? __( 'In n8n, no credential is set on %s: add one there, publish the workflow, then try again.', 'post-quality-manager' )
                /* translators: %s: n8n node names, e.g. "Update WP Post (Rewrite)" */
                : __( 'In n8n, no credential is set on %s: add one there, publish the workflow, then test again.', 'post-quality-manager' ),
            wp_sprintf( '%l', $names )
        );
    }

    /** Whether a node calls Claude: an HTTP Request to api.anthropic.com. */
    private static function is_claude( array $node ): bool {
        return str_contains( (string) ( $node['parameters']['url'] ?? '' ), 'api.anthropic.com' );
    }

    /** The webhook URL's path after /webhook/, '' when it has none. */
    private static function webhook_segment( string $webhook_url ): string {
        $path = (string) wp_parse_url( $webhook_url, PHP_URL_PATH );
        $at   = strpos( $path, '/webhook/' );

        return false === $at ? '' : trim( substr( $path, $at + strlen( '/webhook/' ) ), '/' );
    }

    /**
     * The active workflow whose Webhook trigger path is the URL's segment
     * after /webhook/: {id, name, path, nodes} (its published nodes), null
     * when none listens there. Ceiling: one page of 250 active workflows
     * (n8n's maximum `limit`); following `nextCursor` is the upgrade path.
     *
     * @return array{id: string, name: string, path: string, nodes: array[]}|WP_Error|null
     *         WP_Error (502 pqm_adoption_failed) when n8n did not answer.
     */
    private static function workflow_at( string $webhook_url ): array|WP_Error|null {
        $path = self::webhook_segment( $webhook_url );
        if ( '' === $path ) {
            return null;
        }

        $response = self::request( 'GET', self::ACTIVE_WORKFLOWS );
        if ( ! self::answered( $response ) ) {
            return new WP_Error( 'pqm_adoption_failed', self::could_not( 'read', $response ), [ 'status' => 502 ] );
        }
        foreach ( (array) ( $response['json']['data'] ?? [] ) as $workflow ) {
            $id      = is_array( $workflow ) && is_scalar( $workflow['id'] ?? null ) ? (string) $workflow['id'] : '';
            $nodes   = '' !== $id ? self::published_nodes( $workflow ) : [];
            $trigger = self::trigger( $nodes );
            if ( null !== $trigger && trim( (string) ( $trigger['parameters']['path'] ?? '' ), '/' ) === $path ) {
                return [ 'id' => $id, 'name' => (string) ( $workflow['name'] ?? '' ), 'path' => $path, 'nodes' => $nodes ];
            }
        }

        return null;
    }

    /**
     * A workflow's published nodes (`activeVersion`, else the draft `nodes`
     * when n8n gives no published version), disabled ones left out.
     *
     * @return array[]
     */
    private static function published_nodes( array $workflow ): array {
        $nodes = $workflow['activeVersion']['nodes'] ?? $workflow['nodes'] ?? [];

        return array_values( array_filter( is_array( $nodes ) ? $nodes : [], static fn( $node ) => is_array( $node ) && empty( $node['disabled'] ) ) );
    }

    /** The Webhook trigger among these nodes, null when there is none. */
    private static function trigger( array $nodes ): ?array {
        foreach ( $nodes as $node ) {
            if ( 'n8n-nodes-base.webhook' === ( $node['type'] ?? '' ) ) {
                return $node;
            }
        }

        return null;
    }

    /** Whether a node's `credentials` link at least one credential by id. */
    private static function has_credential( array $credentials ): bool {
        foreach ( $credentials as $credential ) {
            if ( is_array( $credential ) && '' !== (string) ( $credential['id'] ?? '' ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether a request() came back 2xx with a JSON object.
     *
     * @param array|WP_Error $response
     */
    private static function answered( $response ): bool {
        return ! is_wp_error( $response ) && $response['code'] >= 200 && $response['code'] < 300 && is_array( $response['json'] );
    }

    /* ─── Adoption of a hand-made workflow (entry 8) ─────────────────── */

    /**
     * What the plugin would manage: the active workflow listening on the
     * saved webhook URL's path, and the credentials its own nodes carry —
     * the trigger's is the webhook one, the Claude nodes' the Anthropic one,
     * the node saving a rewrite (httpBasicAuth) the WordPress one (D1).
     * One GET, the list read of the diagnosis; credential names are shown,
     * never matched. A wiring the diagnosis would report is refused with its
     * sentence, and so are Claude nodes on different credentials; a node
     * saving a rewrite without one leaves `wordpress` out (D2).
     *
     * @return array|WP_Error {workflow: {id, name, path}, credentials: {webhook: {id, name}, anthropic: {id, name}, wordpress?: {id, name}}}
     */
    public static function propose_adoption(): array|WP_Error {
        $blocked = self::adoption_blocked();
        if ( null !== $blocked ) {
            return $blocked;
        }
        $workflow = self::workflow_at( (string) pqm_get_settings()['n8n_webhook_url'] );
        if ( is_wp_error( $workflow ) ) {
            return $workflow;
        }
        // No Claude node: not a Post Quality workflow, whatever listens there.
        $claude = null === $workflow ? [] : array_values( array_filter( $workflow['nodes'], [ __CLASS__, 'is_claude' ] ) );
        if ( [] === $claude ) {
            return new WP_Error( 'pqm_workflow_not_found', __( 'No published workflow in n8n listens on your n8n Webhook URL. Check the URL, or publish the workflow in n8n, then try again.', 'post-quality-manager' ), [ 'status' => 404 ] );
        }

        $sentence = self::miswiring( $workflow['nodes'], true, true );
        if ( '' === $sentence && count( array_unique( array_map( static fn( $node ) => (string) ( $node['credentials']['httpHeaderAuth']['id'] ?? '' ), $claude ) ) ) > 1 ) {
            $sentence = sprintf(
                /* translators: %s: n8n node names, e.g. "Claude - Review Agent and Claude - SEO Agent" */
                __( 'In n8n, %s use different credentials: pick one Anthropic credential on all of them, publish the workflow, then try again.', 'post-quality-manager' ),
                wp_sprintf( '%l', array_map( static fn( $node ) => (string) ( $node['name'] ?? '' ), $claude ) )
            );
        }
        $trigger     = self::trigger( $workflow['nodes'] );
        $credentials = [
            'webhook'   => self::credential_of( $trigger, 'httpHeaderAuth' ),
            'anthropic' => self::credential_of( $claude[0], 'httpHeaderAuth' ),
        ];
        // A trigger without authentication, or Claude nodes on another credential type: nothing to map.
        foreach ( [ 'webhook' => [ $trigger ], 'anthropic' => $claude ] as $role => $nodes ) {
            if ( '' === $sentence && null === $credentials[ $role ] ) {
                $sentence = self::no_credential( array_map( static fn( $node ) => (string) ( $node['name'] ?? '' ), $nodes ), true );
            }
        }
        if ( '' !== $sentence ) {
            return new WP_Error( 'pqm_workflow_miswired', $sentence, [ 'status' => 409 ] );
        }

        foreach ( $workflow['nodes'] as $node ) {
            if ( 'httpBasicAuth' === ( $node['parameters']['genericAuthType'] ?? '' ) ) {
                $credentials['wordpress'] = self::credential_of( $node, 'httpBasicAuth' );
                break;
            }
        }

        return [
            'workflow'    => [ 'id' => $workflow['id'], 'name' => $workflow['name'], 'path' => $workflow['path'] ],
            'credentials' => array_filter( $credentials ),
        ];
    }

    /**
     * Store the setup map of the workflow the owner confirmed, in the shape
     * provision() stores, with `wizard_created` false. The proposal is read
     * again first, so the map is what n8n holds now: another workflow at the
     * URL than the one shown refuses (409 pqm_adoption_changed), a failed
     * read answers its own error, and a map stored meanwhile is kept (409
     * pqm_already_provisioned). Nothing else is written; n8n is only read.
     *
     * @return array|WP_Error The stored setup map.
     */
    public static function adopt( string $workflow_id ): array|WP_Error {
        $proposal = self::propose_adoption();
        if ( is_wp_error( $proposal ) ) {
            return $proposal;
        }
        if ( $proposal['workflow']['id'] !== $workflow_id ) {
            return new WP_Error( 'pqm_adoption_changed', __( 'n8n changed since you looked: another workflow listens on your n8n Webhook URL now. Press Let the plugin manage this workflow again to see it.', 'post-quality-manager' ), [ 'status' => 409 ] );
        }

        $setup = [
            'workflow_id'    => $workflow_id,
            'credentials'    => array_map( static fn( $credential ) => $credential['id'], $proposal['credentials'] ),
            'path'           => $proposal['workflow']['path'],
            'wizard_created' => false,
            'at'             => time(),
        ];
        // add_option(), not update_option(): a map a concurrent run stored during the read is never rewritten.
        if ( ! add_option( self::SETUP_OPTION, $setup, '', false ) ) {
            return self::already_provisioned();
        }

        return $setup;
    }

    /**
     * Why the plugin may not take the site's workflow over, null when it
     * may: a setup map already names one (as provision() refuses), no key
     * with a passing check, no saved webhook URL. Calls nothing.
     */
    private static function adoption_blocked(): ?WP_Error {
        if ( false !== get_option( self::SETUP_OPTION, false ) ) {
            return self::already_provisioned();
        }
        if ( ! self::connected_key() ) {
            return self::not_connected();
        }
        if ( '' === (string) pqm_get_settings()['n8n_webhook_url'] ) {
            return new WP_Error( 'pqm_webhook_url_missing', __( 'Save your n8n Webhook URL first: the plugin finds your workflow by it.', 'post-quality-manager' ), [ 'status' => 400 ] );
        }

        return null;
    }

    /**
     * The credential of this type a node links by id, {id, name}; null when none.
     *
     * @return array{id: string, name: string}|null
     */
    private static function credential_of( array $node, string $type ): ?array {
        $credential = $node['credentials'][ $type ] ?? null;
        $id         = is_array( $credential ) && is_scalar( $credential['id'] ?? null ) ? (string) $credential['id'] : '';

        return '' === $id ? null : [ 'id' => $id, 'name' => (string) ( $credential['name'] ?? '' ) ];
    }

    private static function already_provisioned(): WP_Error {
        return new WP_Error( 'pqm_already_provisioned', __( 'n8n is already set up for this site.', 'post-quality-manager' ), [ 'status' => 409 ] );
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
     * updated. One run at a time (bug 72): the run holds LOCK_OPTION from
     * before its first outgoing call until the map is stored or it failed,
     * and a run that finds the lock held is refused as already provisioned.
     *
     * @return array|WP_Error The stored setup map (SETUP_OPTION).
     */
    public static function provision( string $provider_key ): array|WP_Error {
        if ( false !== get_option( self::SETUP_OPTION, false ) ) {
            return self::already_provisioned();
        }
        if ( '' === self::connection()['api_key'] ) {
            return self::not_connected();
        }
        if ( ! self::claim() ) {
            return self::already_provisioned();
        }
        $setup = self::run( $provider_key );
        delete_option( self::LOCK_OPTION );

        return $setup;
    }

    /**
     * Claim the provisioning lock. add_option() writes only a key that does
     * not exist yet, but it is not atomic (a read, then the write): the lock
     * narrows bug 72's window from the whole run to one database round trip,
     * it does not close it. run() re-reads the setup map under the lock for
     * the same reason. A lock whose `at` is older than LOCK_TTL was left by a
     * run PHP killed mid-way (no exit of run() leaves it): taken over.
     * Ceiling: a run killed by PHP keeps the lock 5 minutes; story 71's Start over is the way out before that.
     */
    private static function claim(): bool {
        $lock = [ 'at' => time() ];
        if ( add_option( self::LOCK_OPTION, $lock, '', false ) ) {
            return true;
        }
        $held = get_option( self::LOCK_OPTION, false );
        if ( is_array( $held ) && (int) ( $held['at'] ?? 0 ) > time() - self::LOCK_TTL ) {
            return false;
        }
        update_option( self::LOCK_OPTION, $lock, false );

        return true;
    }

    /** provision()'s body, under the lock: every return releases it there. */
    private static function run( string $provider_key ): array|WP_Error {
        // Read again under the lock: a run that finished between the gate and the claim stored a map (bug 72).
        if ( false !== get_option( self::SETUP_OPTION, false ) ) {
            return self::already_provisioned();
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

        $host        = pqm_site_host();
        $names       = self::credential_names( $host );
        $credentials = [
            'webhook'   => [ 'name' => $names['webhook'], 'type' => 'httpHeaderAuth', 'data' => [ 'name' => 'X-PQM-Token', 'value' => (string) pqm_get_settings()['webhook_token'] ] ],
            'anthropic' => [ 'name' => $names['anthropic'], 'type' => 'httpHeaderAuth', 'data' => [ 'name' => 'x-api-key', 'value' => $provider_key ] ],
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

        $body = self::linked_template( $workflow, $ids );
        if ( is_wp_error( $body ) ) {
            return self::fail( 'link', $body->get_error_message(), $credentials, $created );
        }
        $response    = self::request( 'POST', '/workflows', $body );
        $workflow_id = self::created_id( $response );
        if ( null === $workflow_id ) {
            return self::fail( 'workflow', self::stopped( 'workflow', $response ), $credentials, $created );
        }

        $response = self::request( 'POST', '/workflows/' . rawurlencode( $workflow_id ) . '/activate', (object) [] );
        if ( is_wp_error( $response ) || $response['code'] < 200 || $response['code'] >= 300 ) {
            // n8n refuses with 409 when another active workflow already listens on this
            // site's webhook path (bug 75): a retry repeats it, so the way out is named instead.
            if ( ! is_wp_error( $response ) && 409 === $response['code'] ) {
                return self::fail(
                    'activate',
                    __( 'The n8n setup stopped while activating the workflow: another active workflow on this n8n instance already listens on this site\'s webhook path.', 'post-quality-manager' ),
                    $credentials,
                    $created,
                    $workflow_id,
                    __( 'If that workflow is this site\'s, paste its production URL as the n8n Webhook URL and choose Let the plugin manage this workflow on the n8n Workflow page; otherwise remove it in n8n, then try again.', 'post-quality-manager' )
                );
            }

            return self::fail( 'activate', self::stopped( 'activate', $response ), $credentials, $created, $workflow_id );
        }

        $setup = [
            'workflow_id'    => $workflow_id,
            'credentials'    => $created,
            'path'           => pqm_webhook_path(),
            'wizard_created' => true,
            'at'             => time(),
        ];
        if ( pqm_fs()->is__premium_only() ) {
            // Bug 73: the map names the Application Password the run created, so a later
            // removal of the setup (uninstall now, Start over later) revokes it. Never sent anywhere.
            if ( isset( $credentials['wordpress']['uuid'] ) ) {
                $setup['application_password'] = [ 'uuid' => $credentials['wordpress']['uuid'], 'user_id' => $credentials['wordpress']['user_id'] ];
            }
        }
        update_option( self::SETUP_OPTION, $setup, false );

        return $setup;
    }

    /**
     * The deterministic credential names, by role: what the template's
     * nodes carry once __PQM_SITE_HOST__ is filled, what provision()
     * creates, and what the Workflow File section shows.
     *
     * @return array{webhook: string, anthropic: string, wordpress: string}
     */
    private static function credential_names( string $host ): array {
        return [ 'webhook' => 'Post Quality webhook - ' . $host, 'anthropic' => 'Anthropic - Claude', 'wordpress' => 'WordPress - ' . $host ];
    }

    /**
     * The decoded template (pqm_workflow_json(), objects) rendered for n8n:
     * the four keys a create or an update takes — `name`, `nodes`,
     * `connections`, `settings`, never `active` (the public API sets it
     * through activate only) — with every credentialed node linked by id.
     * Linked by credential name, the one thing the template carries: `$ids`
     * is template credential name => id, null for a credential deliberately
     * left name-only (D1); `$names` is template credential name => the name
     * n8n shows beside that id, the template's own when absent (entry 9: an
     * adopted workflow's credentials carry the owner's names). Both builds'
     * templates carry two httpHeaderAuth credentials, so the type alone
     * would not tell them apart. A template credential `$ids` does not
     * name is the plugin's own fault: WP_Error `pqm_template_link`.
     *
     * @return array{name: mixed, nodes: array, connections: object, settings: object}|WP_Error
     */
    private static function linked_template( object $workflow, array $ids, array $names = [] ): array|WP_Error {
        foreach ( $workflow->nodes as $node ) {
            foreach ( (array) ( $node->credentials ?? [] ) as $type => $ref ) {
                $name = (string) ( $ref->name ?? '' );
                if ( ! array_key_exists( $name, $ids ) ) {
                    return new WP_Error( 'pqm_template_link', sprintf(
                        /* translators: %s: credential name from the workflow template */
                        __( 'The workflow template names a credential the setup does not create (%s).', 'post-quality-manager' ),
                        $name
                    ), [ 'status' => 500 ] );
                }
                if ( null !== $ids[ $name ] ) {
                    $node->credentials->{$type} = [ 'id' => $ids[ $name ], 'name' => $names[ $name ] ?? $name ];
                }
            }
        }

        return [
            'name'        => $workflow->name,
            'nodes'       => $workflow->nodes,
            'connections' => $workflow->connections,
            'settings'    => $workflow->settings,
        ];
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
     * is not created and its node stays name-only. `uuid` and `user_id` name
     * the created Application Password for its revoke (a failed run, the
     * setup map's removal); they never go to n8n.
     */
    private static function wordpress_credential__premium_only( string $host ): array|WP_Error {
        $credential = [ 'name' => self::credential_names( $host )['wordpress'], 'type' => 'httpBasicAuth', 'data' => null ];
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
        $credential['data']    = [ 'user' => $user->user_login, 'password' => $created[0] ];
        $credential['uuid']    = $created[1]['uuid'];
        $credential['user_id'] = (int) $user->ID;

        return $credential;
    }

    /**
     * The one deleter of an Application Password the plugin created (bug 73,
     * D2): a failed run's (fail()) or the setup map's
     * (revoke_setup_password__premium_only()). WordPress stores only its
     * hash, so a later provisioning can never reuse it: revoked, never kept.
     */
    private static function revoke_application_password__premium_only( int $user_id, string $uuid ): void {
        WP_Application_Passwords::delete_application_password( $user_id, $uuid );
    }

    /**
     * Revoke the Application Password the setup map names, if it names one:
     * what removes the map calls it first (PQM_Activator::uninstall() now;
     * story 71's Start over later). A map without one (set up by an earlier
     * version, adopted, or without Application Passwords) or no map: no call.
     */
    public static function revoke_setup_password__premium_only(): void {
        $setup    = get_option( self::SETUP_OPTION, false );
        $password = is_array( $setup ) ? ( $setup['application_password'] ?? null ) : null;
        if ( is_array( $password ) && isset( $password['uuid'], $password['user_id'] ) ) {
            self::revoke_application_password__premium_only( (int) $password['user_id'], (string) $password['uuid'] );
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
     * @param string  $cause   The sentence naming what went wrong.
     * @param array   $created role => id of the credentials this run created.
     * @param ?string $then    The way out, replacing "Try again in a moment." when a retry would repeat the refusal (bug 75).
     */
    private static function fail( string $step, string $cause, array $credentials, array $created, ?string $workflow_id = null, ?string $then = null ): WP_Error {
        // Before n8n's rollback, and whatever it gives: the WordPress credential is useless once the run failed.
        if ( pqm_fs()->is__premium_only() ) {
            if ( isset( $credentials['wordpress']['uuid'] ) ) {
                self::revoke_application_password__premium_only( (int) $credentials['wordpress']['user_id'], (string) $credentials['wordpress']['uuid'] );
            }
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
                : ( $then ?? __( 'Try again in a moment.', 'post-quality-manager' ) ),
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
     * Whether a DELETE left the object gone: 2xx, or n8n's own 404 (gone()).
     *
     * @param array|WP_Error $response
     */
    private static function removed( $response ): bool {
        return self::gone( $response ) || ( ! is_wp_error( $response ) && $response['code'] >= 200 && $response['code'] < 300 );
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
     * Why a provisioning step stopped the run, in plain words; fail() adds
     * what was rolled back. A single call on a site already set up (a read,
     * a token rotation, a workflow update) says could_not() instead.
     *
     * @param array|WP_Error $response
     */
    private static function stopped( string $step, $response ): string {
        return sprintf(
            /* translators: 1: setup step, e.g. "creating the webhook credential"; 2: what n8n answered, e.g. "n8n answered HTTP 400" */
            __( 'The n8n setup stopped while %1$s (%2$s).', 'post-quality-manager' ),
            self::label( $step ),
            self::answer( $step, $response )
        );
    }

    /* ─── Webhook token rotation (entry 10) ──────────────────────────── */

    /**
     * Write a new webhook token into the setup map's webhook credential in
     * n8n: one PATCH, nothing else there is read or changed. The token is
     * wp_generate_uuid4(), as activation makes it. Answered after a passing
     * PATCH and never stored here: the caller saves it (PATCH first, save
     * second, so a refused PATCH leaves both sides as they were). It is in
     * no error, and n8n's reply body is never repeated.
     *
     * @return string|WP_Error The new token; why nothing changed otherwise.
     */
    public static function rotate_token(): string|WP_Error {
        $blocked = self::managed_blocked();
        if ( null !== $blocked ) {
            return $blocked;
        }
        $token    = wp_generate_uuid4();
        $id       = (string) get_option( self::SETUP_OPTION )['credentials']['webhook'];
        $response = self::request( 'PATCH', '/credentials/' . rawurlencode( $id ), [ 'data' => [ 'name' => 'X-PQM-Token', 'value' => $token ] ] );
        if ( self::answered( $response ) ) {
            return $token;
        }
        $sentence = self::gone( $response )
            ? __( 'n8n no longer has the webhook credential the plugin manages. Check the Webhook Trigger\'s credential in n8n, then try again.', 'post-quality-manager' )
            : self::could_not( 'credential', $response );

        return new WP_Error( 'pqm_rotation_failed', $sentence, [ 'status' => 502 ] );
    }

    /**
     * Why the plugin may not act on the workflow it manages — rotate the
     * webhook token (entry 10) or update the workflow (entry 9) — null when
     * it may: no key with a passing check, no setup map naming a webhook
     * credential, or a saved webhook URL off the map's path (entry 8, D3:
     * the site must call the workflow the action changes). Calls nothing;
     * the status block asks it to choose the update notice's fix.
     */
    public static function managed_blocked(): ?WP_Error {
        if ( ! self::connected_key() ) {
            return self::not_connected();
        }
        $setup   = get_option( self::SETUP_OPTION, false );
        $webhook = is_array( $setup ) ? ( $setup['credentials']['webhook'] ?? null ) : null;
        if ( ! is_scalar( $webhook ) || '' === (string) $webhook || '' === (string) ( $setup['workflow_id'] ?? '' ) ) {
            return new WP_Error( 'pqm_workflow_not_managed', __( 'The plugin does not manage your n8n workflow yet. Let it set the workflow up, or manage the one you set up by hand, then try again.', 'post-quality-manager' ), [ 'status' => 409 ] );
        }
        $path = self::webhook_segment( (string) pqm_get_settings()['n8n_webhook_url'] );
        if ( '' === $path || (string) ( $setup['path'] ?? '' ) !== $path ) {
            return new WP_Error( 'pqm_webhook_url_changed', __( 'Your n8n Webhook URL no longer points at the workflow the plugin manages, so nothing was changed. Save that workflow\'s production URL as the n8n Webhook URL, then try again.', 'post-quality-manager' ), [ 'status' => 409 ] );
        }

        return null;
    }

    /* ─── One-click workflow update (entry 9) ────────────────────────── */

    /**
     * Write the build's template onto the workflow the setup map names, in
     * place: one GET of that workflow — the names its credentials carry (the
     * map holds ids only, and an adopted workflow's names are the owner's)
     * and its own name, both kept (D2); a JSON 404 says it is gone — then
     * one PUT of linked_template(): the map's credential id on every
     * credentialed node, the trigger on the map's path (D1: an adopted
     * workflow keeps its own, never pqm_webhook_path()), the four keys n8n
     * takes and never `active` — n8n republishes a PUT workflow by itself
     * (spike 56), so the production URL stays. A role the map lacks leaves
     * its node name-only, as provisioning's D1 does. Nothing else in n8n is
     * read or changed; nothing is stored here (the caller pings and records
     * the version). n8n's reply body is never repeated.
     *
     * @return array|WP_Error The setup map of the updated workflow.
     */
    public static function update_workflow(): array|WP_Error {
        $blocked = self::managed_blocked();
        if ( null !== $blocked ) {
            return $blocked;
        }
        $setup = get_option( self::SETUP_OPTION );
        // Objects, not arrays: an empty `{}` in the template must stay one.
        $workflow = json_decode( (string) pqm_workflow_json( (string) $setup['path'] ) );
        if ( ! is_object( $workflow ) ) {
            return new WP_Error( 'pqm_update_failed', __( 'The workflow template is missing from the plugin. Reinstall the plugin, then try again.', 'post-quality-manager' ), [ 'status' => 500 ] );
        }

        $path     = '/workflows/' . rawurlencode( (string) $setup['workflow_id'] );
        $response = self::request( 'GET', $path );
        if ( ! self::answered( $response ) ) {
            $sentence = self::gone( $response )
                ? __( 'The workflow the plugin manages is no longer in n8n. Set the workflow up again, or let the plugin manage the one at your n8n Webhook URL, then try again.', 'post-quality-manager' )
                : self::could_not( 'read', $response );

            return new WP_Error( 'pqm_update_failed', $sentence, [ 'status' => 502 ] );
        }
        $carried = []; // credential id => the name the read workflow's nodes carry for it
        foreach ( self::published_nodes( $response['json'] ) as $node ) {
            foreach ( (array) ( $node['credentials'] ?? [] ) as $credential ) {
                if ( is_array( $credential ) && is_scalar( $credential['id'] ?? null ) ) {
                    $carried[ (string) $credential['id'] ] = (string) ( $credential['name'] ?? '' );
                }
            }
        }
        $ids   = []; // template credential name => the map's id for that role; null = left name-only (D1)
        $names = [];
        foreach ( self::credential_names( pqm_site_host() ) as $role => $name ) {
            $ids[ $name ]   = isset( $setup['credentials'][ $role ] ) ? (string) $setup['credentials'][ $role ] : null;
            $names[ $name ] = $carried[ $ids[ $name ] ] ?? '';
        }
        $body = self::linked_template( $workflow, $ids, $names );
        if ( is_wp_error( $body ) ) {
            return $body;
        }
        $body['name'] = (string) ( $response['json']['name'] ?? '' ) ?: $body['name'];

        $response = self::request( 'PUT', $path, $body );
        if ( ! self::answered( $response ) ) {
            return new WP_Error( 'pqm_update_failed', self::could_not( 'update', $response ), [ 'status' => 502 ] );
        }

        return $setup;
    }

    /* ─── Shared helpers (entry 11) ──────────────────────────────────── */

    /**
     * Why a single call on a site already set up — a read, a token
     * rotation, a workflow update — did not go through, with the fix:
     * "The plugin could not finish updating the workflow (n8n answered
     * HTTP 500). Try again in a moment." Provisioning says stopped().
     *
     * @param array|WP_Error $response
     */
    private static function could_not( string $step, $response ): string {
        return sprintf(
            /* translators: 1: the action, e.g. "updating the workflow"; 2: what n8n answered, e.g. "n8n answered HTTP 500" */
            __( 'The plugin could not finish %1$s (%2$s). Try again in a moment.', 'post-quality-manager' ),
            self::label( $step ),
            self::answer( $step, $response )
        );
    }

    /** What stopped() and could_not() call a step, e.g. "creating the workflow". */
    private static function label( string $step ): string {
        $labels = [
            'credential_webhook'   => __( 'creating the webhook credential', 'post-quality-manager' ),
            'credential_anthropic' => __( 'creating the Anthropic credential', 'post-quality-manager' ),
            'credential_wordpress' => __( 'creating the WordPress credential', 'post-quality-manager' ),
            'workflow'             => __( 'creating the workflow', 'post-quality-manager' ),
            'activate'             => __( 'activating the workflow', 'post-quality-manager' ),
            'read'                 => __( 'reading your workflows', 'post-quality-manager' ), // entry 8
            'credential'           => __( 'updating the webhook credential', 'post-quality-manager' ), // entry 10
            'update'               => __( 'updating the workflow', 'post-quality-manager' ), // entry 9
        ];

        return $labels[ $step ] ?? $step;
    }

    /**
     * What n8n answered a step, for stopped() and could_not(): unreachable,
     * an HTTP status, or a 2xx without the JSON the step needs. n8n's reply
     * body is not repeated (it may quote the request).
     *
     * @param array|WP_Error $response
     */
    private static function answer( string $step, $response ): string {
        if ( is_wp_error( $response ) ) {
            /* translators: %s: connection error message */
            return sprintf( __( 'n8n could not be reached: %s', 'post-quality-manager' ), $response->get_error_message() );
        }
        if ( $response['code'] >= 200 && $response['code'] < 300 ) {
            return match ( $step ) {
                'read'       => __( 'n8n did not answer with your workflows', 'post-quality-manager' ),
                'credential' => __( 'n8n did not confirm the change', 'post-quality-manager' ),
                'update'     => __( 'n8n did not confirm the update', 'post-quality-manager' ),
                default      => __( 'n8n did not return what it created', 'post-quality-manager' ),
            };
        }

        /* translators: %d: HTTP status code */
        return sprintf( __( 'n8n answered HTTP %d', 'post-quality-manager' ), $response['code'] );
    }

    /**
     * Whether n8n says the object is gone: a JSON 404 (n8n 2.42.3 answers
     * `404 {"message":"Not Found"}` for an id it does not know; a non-JSON
     * 404 comes from a proxy, so the object may still be there).
     *
     * @param array|WP_Error $response
     */
    private static function gone( $response ): bool {
        return ! is_wp_error( $response ) && 404 === $response['code'] && null !== $response['json'];
    }

    /** Whether an n8n API key is stored and its last probe passed. */
    private static function connected_key(): bool {
        $connection = self::connection();

        return '' !== $connection['api_key'] && ! empty( $connection['probe']['ok'] );
    }
}
