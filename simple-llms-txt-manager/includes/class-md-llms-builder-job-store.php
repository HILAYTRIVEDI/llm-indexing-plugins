<?php
declare(strict_types=1);

/**
 * Job persistence for the LLM Builder.
 *
 * @package MD_LLMs_Txt
 */

if (! defined('ABSPATH') ) {
    exit;
}

/**
 * Manage builder jobs within a dedicated table.
 */
class MD_LLMs_Builder_Job_Store
{


    /**
     * @var string
     */
    private $table;

    /**
     * Constructor.
     */
    public function __construct()
    {
        global $wpdb;
        $this->table = $wpdb->prefix . 'md_llms_jobs';
    }

    /**
     * Ensure the builder jobs table exists.
     */
    public function ensure_schema(): void
    {
        global $wpdb;

        include_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$this->table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			status VARCHAR(20) NOT NULL DEFAULT 'pending',
			args LONGTEXT NOT NULL,
			logs LONGTEXT NOT NULL,
			output_file TEXT NULL,
			artifacts_dir TEXT NULL,
			error_message LONGTEXT NULL,
			page_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
			page_urls LONGTEXT NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id)
		) {$charset_collate};";

        dbDelta($sql); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.dbDelta_dbDelta
    }

    /**
     * Create a new job.
     *
     * @param array<string, mixed> $args Arguments for the builder.
     *
     * @return int
     */
    public function create( array $args ): int
    {
        global $wpdb;

        $now      = current_time('mysql', true);
        $inserted = $wpdb->insert(
            $this->table,
            array(
            'status'        => 'pending',
            'args'          => wp_json_encode($args),
            'logs'          => wp_json_encode(array()),
            'output_file'   => null,
            'artifacts_dir' => null,
            'error_message' => null,
            'page_count'    => 0,
            'page_urls'     => wp_json_encode(array()),
            'created_at'    => $now,
            'updated_at'    => $now,
            ),
            array( '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s' )
        );

        if (false === $inserted ) {
            return 0;
        }

        return (int) $wpdb->insert_id;
    }

    /**
     * Fetch a job by ID.
     *
     * @param int $job_id Job ID.
     *
     * @return array<string, mixed>|null
     */
    public function get( int $job_id ): ?array
    {
        global $wpdb;

        $row = $wpdb->get_row($wpdb->prepare("SELECT id, status, args, logs, output_file, artifacts_dir, error_message, page_count, page_urls, created_at, updated_at FROM {$this->table} WHERE id = %d", $job_id), ARRAY_A); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery

        if (! $row ) {
            return null;
        }

        return $this->hydrate_row($row);
    }

    /**
     * Retrieve recent jobs.
     *
     * @param int $limit Number of jobs.
     *
     * @return array<int, array<string, mixed>>
     */
    public function recent( int $limit = 10 ): array
    {
        global $wpdb;

        $limit = max(1, min(50, $limit));

        $rows = $wpdb->get_results($wpdb->prepare("SELECT id, status, args, logs, output_file, artifacts_dir, error_message, page_count, page_urls, created_at, updated_at FROM {$this->table} ORDER BY created_at DESC LIMIT %d", $limit), ARRAY_A); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        if (empty($rows) ) {
            return array();
        }

        return array_map(array( $this, 'hydrate_row' ), $rows);
    }

    /**
     * Update a job.
     *
     * @param int                     $job_id Job ID.
     * @param array<string, mixed>    $data   Data.
     * @param array<int, string>|null $format Overrides.
     */
    public function update( int $job_id, array $data, array $format = null ): void
    {
        global $wpdb;

        $data['updated_at'] = current_time('mysql', true);

        if (null === $format ) {
            $format = array_fill(0, count($data), '%s');
        } else {
            $format[] = '%s';
        }

        $wpdb->update(
            $this->table,
            $data,
            array( 'id' => $job_id ),
            $format,
            array( '%d' )
        );
    }

    /**
     * Append a log entry.
     *
     * @param int                  $job_id  Job ID.
     * @param string               $message Message.
     * @param array<string, mixed> $context Context.
     */
    public function append_log( int $job_id, string $message, array $context = array() ): void
    {
        global $wpdb;

        if ('' === $message ) {
            return;
        }

        $row = $wpdb->get_row($wpdb->prepare("SELECT logs FROM {$this->table} WHERE id = %d", $job_id), ARRAY_A); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery

        $logs = array();
        if (isset($row['logs']) ) {
            $decoded = json_decode($row['logs'], true);
            if (is_array($decoded) ) {
                $logs = $decoded;
            }
        }

        $logs[] = array(
        'timestamp' => current_time('mysql', true),
        'message'   => $message,
        'context'   => $context,
        );

        $this->update(
            $job_id,
            array(
            'logs' => wp_json_encode($logs),
            ),
            array( '%s' )
        );
    }

    /**
     * Hydrate DB row.
     *
     * @param array<string, mixed> $row Row.
     *
     * @return array<string, mixed>
     */
    private function hydrate_row( array $row ): array
    {
        $row['args'] = $this->maybe_decode($row['args']);
        $row['logs'] = $this->maybe_decode($row['logs']);

        if (isset($row['page_urls']) ) {
            $row['page_urls'] = $this->maybe_decode($row['page_urls']);
        }

        return $row;
    }

    /**
     * Maybe decode JSON.
     *
     * @param mixed $value Value.
     *
     * @return mixed
     */
    private function maybe_decode( $value )
    {
        if (is_string($value) ) {
            $decoded = json_decode($value, true);
            if (null !== $decoded ) {
                return $decoded;
            }
        }

        return $value;
    }
}
