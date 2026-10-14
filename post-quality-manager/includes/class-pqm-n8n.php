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
 * creates, changes and deletes nothing on n8n. Later entries call request().
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
            return new WP_Error( 'pqm_n8n_not_connected', __( 'No n8n API key is set. Connect your n8n instance on the n8n Workflow page.', 'post-quality-manager' ) );
        }

        return self::call( $connection['base_url'], $connection['api_key'], $method, self::API_PATH . $path, $body );
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
}
