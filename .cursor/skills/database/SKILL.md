---
name: database
description: Database schema, migrations, and query conventions for the BookIt booking system plugin. Use when creating or modifying database tables, writing migrations, querying with wpdb, or working with the booking system data model.
---

# Database Conventions

## Context7 Integration

Use Context7 to look up WordPress database functions when needed:
- Resolve `wordpress` and query topics like "wpdb prepare", "dbDelta usage", or "WordPress database schema".

## Schema Reference

The full schema is documented in `database/schema.sql`. Always update this file when modifying the schema.

### Tables (all prefixed with `wp_bookings_`)

| Table | Purpose | Key columns |
|-------|---------|-------------|
| `services` | Service catalog | name, price, duration, deposit_amount, deposit_type |
| `categories` | Service categories | name, display_order |
| `service_categories` | Junction: service-to-category (M:N) | service_id, category_id |
| `staff` | Staff members | email, first_name, last_name, photo_url, bio, title, role |
| `staff_services` | Junction: staff-to-service (M:N) with pricing | staff_id, service_id, custom_price |
| `customers` | Customer database (GDPR-aware) | email, phone, marketing_consent |
| `bookings` | Main bookings table | customer_id, service_id, staff_id, booking_date, start_time, status |
| `payments` | Payment transactions | booking_id, amount, payment_type, payment_status, stripe_payment_intent_id |
| `working_hours` | Staff availability schedule | staff_id, day_of_week (0-6), start_time, end_time |
| `settings` | Key-value settings store | setting_key, setting_value, autoload |
| `idempotency` | Idempotency tracking | idempotency_key, operation_type, status, expires_at |

### Critical Constraints

- `bookings`: UNIQUE on `(staff_id, booking_date, start_time)` -- prevents double-booking at DB level.
- `staff`: UNIQUE on `email`.
- `customers`: UNIQUE on `email`.
- `settings`: UNIQUE on `setting_key`.
- Junction tables: UNIQUE on composite keys (e.g., `service_id, category_id`).

## Soft Deletes

Most tables use soft deletes via a `deleted_at` column:

```php
// Soft delete a record
$wpdb->update(
    $wpdb->prefix . 'bookings_services',
    array( 'deleted_at' => current_time( 'mysql', true ) ),
    array( 'id' => $service_id ),
    array( '%s' ),
    array( '%d' )
);

// Query only non-deleted records (always include this filter)
$wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}bookings_services WHERE deleted_at IS NULL AND is_active = 1"
);
```

**Important**: Always filter by `deleted_at IS NULL` in SELECT queries unless explicitly querying archived records.

## Query Patterns

### Always Use Prepared Statements

```php
global $wpdb;

// Single value
$service = $wpdb->get_row(
    $wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}bookings_services WHERE id = %d",
        $service_id
    ),
    ARRAY_A
);

// Insert with format array
$wpdb->insert(
    $wpdb->prefix . 'bookings_services',
    array(
        'name'      => $name,
        'price'     => $price,
        'duration'  => $duration,
        'is_active' => 1,
    ),
    array( '%s', '%f', '%d', '%d' )
);
$new_id = $wpdb->insert_id;

// Update
$wpdb->update(
    $wpdb->prefix . 'bookings_services',
    array( 'name' => $new_name ),     // Data
    array( 'id' => $service_id ),      // Where
    array( '%s' ),                     // Data format
    array( '%d' )                      // Where format
);
```

### Format Placeholders

| Placeholder | Type | Example columns |
|-------------|------|-----------------|
| `%d` | Integer | id, is_active, duration |
| `%f` | Float | price, custom_price, deposit_amount |
| `%s` | String | name, email, deleted_at (datetime) |

### Table Name Convention

Always use `$wpdb->prefix` to build table names:

```php
$table = $wpdb->prefix . 'bookings_services';  // e.g., wp_bookings_services
```

## Migrations

### File Location

- Migration files: `database/migrations/migration-{description}.php`
- Migration runner: `run-migration.php` (project root)
- DB table init: `includes/class-bookit-database.php`

### Migration Class Template

```php
<?php
/**
 * Migration: Description of Change
 *
 * Run Date: YYYY-MM-DD
 * Sprint: Sprint X, Task Y
 * Reason: Why this migration is needed
 *
 * @package    Bookit_Booking_System
 * @subpackage Bookit_Booking_System/database/migrations
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

class Bookit_Migration_Description {

    private $wpdb;

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
    }

    /**
     * Run the migration.
     *
     * @return bool Success status.
     */
    public function up() {
        $errors = array();

        // Check before altering (idempotent)
        if ( ! $this->column_exists( 'bookings_staff', 'new_column' ) ) {
            $result = $this->wpdb->query(
                "ALTER TABLE {$this->wpdb->prefix}bookings_staff
                 ADD COLUMN new_column VARCHAR(255) NULL AFTER existing_column"
            );
            if ( false === $result ) {
                $errors[] = 'Failed to add new_column: ' . $this->wpdb->last_error;
            }
        }

        return empty( $errors );
    }

    /**
     * Rollback (optional).
     */
    public function down() {
        error_log( 'Rollback not implemented.' );
    }

    /**
     * Check if column exists.
     */
    private function column_exists( $table, $column ) {
        $full_table = $this->wpdb->prefix . $table;
        $results = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SHOW COLUMNS FROM `{$full_table}` LIKE %s",
                $column
            )
        );
        return ! empty( $results );
    }
}
```

### Migration Rules

1. **Always check before altering** -- Use `column_exists()` to make migrations idempotent (safe to run multiple times).
2. **Log progress** -- Use `error_log()` to track what was added or skipped.
3. **Collect errors** -- Don't abort on first error; collect all and report.
4. **Update `database/schema.sql`** -- After any migration, update the reference schema file.
5. **Update `bookit_db_version`** -- Bump the version in `class-bookit-database.php`.
6. **New columns should be nullable** -- Use `NULL DEFAULT NULL` for new columns to avoid breaking existing rows.

## Initial Table Creation

Tables are created via `Bookit_Database` class in `includes/class-bookit-database.php` using WordPress's `dbDelta()`:

```php
require_once ABSPATH . 'wp-admin/includes/upgrade.php';
dbDelta( $sql );
```

**dbDelta rules**:
- Each field on its own line.
- Two spaces between field name and definition.
- PRIMARY KEY must be on its own line with two spaces before.
- KEY indexes use the table name format.

## Common Column Patterns

### Timestamps

Every table includes:
```sql
created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
deleted_at DATETIME NULL DEFAULT NULL,
```

### Active/Inactive Flag

```sql
is_active TINYINT(1) DEFAULT 1,
```

### Display Ordering

```sql
display_order INT DEFAULT 0,
```

## Database Version

The current database version is tracked via `get_option( 'bookit_db_version' )`. Bump this when adding migrations. The `Bookit_Database` class checks this value on plugin activation to determine if migrations need to run.
