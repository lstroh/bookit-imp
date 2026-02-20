/**
 * Bookit Booking System - Database Schema
 * 
 * This file documents the complete database schema for reference.
 * DO NOT run this file directly - tables are created via Bookit_Database class.
 * 
 * Total Tables: 13
 * Last Updated: 2026-02-20
 */

-- ============================================
-- TABLE 1: wp_bookings_services
-- ============================================
CREATE TABLE wp_bookings_services (
	id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	name VARCHAR(255) NOT NULL,
	description TEXT NULL,
	duration INT UNSIGNED NOT NULL COMMENT 'Duration in minutes',
	price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
	deposit_amount DECIMAL(10,2) NULL DEFAULT NULL COMMENT 'Optional deposit amount',
	deposit_type ENUM('fixed','percentage') DEFAULT 'fixed',
	buffer_before INT UNSIGNED DEFAULT 0 COMMENT 'Buffer time before appointment (minutes)',
	buffer_after INT UNSIGNED DEFAULT 0 COMMENT 'Buffer time after appointment (minutes)',
	is_active TINYINT(1) DEFAULT 1,
	display_order INT DEFAULT 0,
	created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	deleted_at DATETIME NULL DEFAULT NULL COMMENT 'Soft delete timestamp',
	PRIMARY KEY (id),
	KEY idx_is_active (is_active),
	KEY idx_deleted_at (deleted_at),
	KEY idx_display_order (display_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLE 2: wp_bookings_categories
-- ============================================
CREATE TABLE wp_bookings_categories (
	id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	name VARCHAR(255) NOT NULL,
	description TEXT NULL,
	display_order INT DEFAULT 0,
	is_active TINYINT(1) DEFAULT 1,
	created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	deleted_at DATETIME NULL DEFAULT NULL,
	PRIMARY KEY (id),
	KEY idx_is_active (is_active),
	KEY idx_deleted_at (deleted_at),
	KEY idx_display_order (display_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLE 3: wp_bookings_service_categories (Junction Table)
-- ============================================
CREATE TABLE wp_bookings_service_categories (
	id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	service_id BIGINT UNSIGNED NOT NULL,
	category_id BIGINT UNSIGNED NOT NULL,
	created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (id),
	UNIQUE KEY unique_service_category (service_id, category_id),
	KEY idx_service_id (service_id),
	KEY idx_category_id (category_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLE 4: wp_bookings_staff
-- ============================================
CREATE TABLE wp_bookings_staff (
	id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	email VARCHAR(255) NOT NULL,
	password_hash VARCHAR(255) NOT NULL,
	first_name VARCHAR(100) NOT NULL,
	last_name VARCHAR(100) NOT NULL,
	phone VARCHAR(20) NULL,
	photo_url VARCHAR(500) NULL COMMENT 'URL to uploaded staff photo',
	bio TEXT NULL COMMENT 'Short bio shown to customers',
	title VARCHAR(100) NULL COMMENT 'Job title (e.g., Senior Stylist)',
	role ENUM('staff','admin') DEFAULT 'staff',
	google_calendar_id VARCHAR(255) NULL COMMENT 'For calendar sync',
	is_active TINYINT(1) DEFAULT 1,
	display_order INT DEFAULT 0,
	created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	deleted_at DATETIME NULL DEFAULT NULL,
	PRIMARY KEY (id),
	UNIQUE KEY unique_email (email),
	KEY idx_role (role),
	KEY idx_is_active (is_active),
	KEY idx_deleted_at (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLE 5: wp_bookings_staff_services (Junction Table)
-- ============================================
CREATE TABLE wp_bookings_staff_services (
	id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	staff_id BIGINT UNSIGNED NOT NULL,
	service_id BIGINT UNSIGNED NOT NULL,
	custom_price DECIMAL(10,2) NULL COMMENT 'Staff-specific price (NULL = use service price)',
	created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (id),
	UNIQUE KEY unique_staff_service (staff_id, service_id),
	KEY idx_staff_id (staff_id),
	KEY idx_service_id (service_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLE 6: wp_bookings_customers
-- ============================================
CREATE TABLE wp_bookings_customers (
	id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	email VARCHAR(255) NOT NULL,
	first_name VARCHAR(100) NOT NULL,
	last_name VARCHAR(100) NOT NULL,
	phone VARCHAR(20) NOT NULL,
	marketing_consent TINYINT(1) DEFAULT 0 COMMENT 'GDPR marketing consent',
	marketing_consent_date DATETIME NULL COMMENT 'When consent was given',
	notes TEXT NULL COMMENT 'Internal staff notes about customer',
	created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	deleted_at DATETIME NULL DEFAULT NULL,
	PRIMARY KEY (id),
	UNIQUE KEY unique_email (email),
	KEY idx_deleted_at (deleted_at),
	KEY idx_phone (phone)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLE 7: wp_bookings (MAIN BOOKINGS TABLE)
-- ============================================
-- CRITICAL: Includes UNIQUE constraint on (staff_id, booking_date, start_time)
-- to prevent double-booking at database level
CREATE TABLE wp_bookings (
	id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	customer_id BIGINT UNSIGNED NOT NULL,
	service_id BIGINT UNSIGNED NOT NULL,
	staff_id BIGINT UNSIGNED NOT NULL,
	booking_date DATE NOT NULL,
	start_time TIME NOT NULL,
	end_time TIME NOT NULL,
	duration INT UNSIGNED NOT NULL COMMENT 'Duration in minutes (cached from service)',
	status ENUM('pending','pending_payment','confirmed','completed','cancelled','no_show') NOT NULL DEFAULT 'pending_payment',
	total_price DECIMAL(10,2) NOT NULL,
	deposit_amount DECIMAL(10,2) NULL DEFAULT NULL COMMENT 'Service deposit config amount',
	deposit_paid DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Actual amount paid as deposit',
	balance_due DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Remaining balance to pay',
	full_amount_paid TINYINT(1) DEFAULT 0,
	payment_method VARCHAR(50) NULL COMMENT 'stripe, paypal, cash, card',
	payment_intent_id VARCHAR(255) NULL COMMENT 'Stripe PaymentIntent ID',
	stripe_session_id VARCHAR(255) NULL DEFAULT NULL COMMENT 'Stripe Checkout session ID for lookup after payment',
	special_requests TEXT NULL COMMENT 'Special requests from customer during booking',
	staff_notes TEXT NULL COMMENT 'Internal staff notes',
	cancellation_reason TEXT NULL,
	cancelled_at DATETIME NULL,
	cancelled_by VARCHAR(50) NULL COMMENT 'customer, staff, system',
	google_calendar_event_id VARCHAR(255) NULL COMMENT 'For calendar sync',
	created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	deleted_at DATETIME NULL DEFAULT NULL,
	PRIMARY KEY (id),
	UNIQUE KEY unique_booking_slot (staff_id, booking_date, start_time),
	KEY idx_customer_id (customer_id),
	KEY idx_service_id (service_id),
	KEY idx_staff_id (staff_id),
	KEY idx_booking_date (booking_date),
	KEY idx_status (status),
	KEY idx_deleted_at (deleted_at),
	KEY idx_date_time (booking_date, start_time),
	KEY idx_payment_intent (payment_intent_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLE 8: wp_bookings_payments
-- ============================================
CREATE TABLE wp_bookings_payments (
	id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	booking_id BIGINT UNSIGNED NOT NULL,
	customer_id BIGINT UNSIGNED NOT NULL,
	amount DECIMAL(10,2) NOT NULL,
	payment_type ENUM('deposit','full_payment','refund') DEFAULT 'full_payment',
	payment_method VARCHAR(50) NOT NULL COMMENT 'stripe, paypal, cash, card',
	payment_status ENUM('pending','completed','failed','refunded','partially_refunded') DEFAULT 'pending',
	stripe_payment_intent_id VARCHAR(255) NULL COMMENT 'Stripe PaymentIntent ID',
	stripe_charge_id VARCHAR(255) NULL COMMENT 'Stripe Charge ID',
	paypal_order_id VARCHAR(255) NULL COMMENT 'PayPal Order ID',
	paypal_capture_id VARCHAR(255) NULL COMMENT 'PayPal Capture ID',
	refund_amount DECIMAL(10,2) NULL DEFAULT NULL,
	refund_reason TEXT NULL,
	refunded_at DATETIME NULL,
	transaction_date DATETIME NOT NULL,
	created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	PRIMARY KEY (id),
	KEY idx_booking_id (booking_id),
	KEY idx_customer_id (customer_id),
	KEY idx_payment_status (payment_status),
	KEY idx_transaction_date (transaction_date),
	KEY idx_stripe_payment_intent (stripe_payment_intent_id),
	KEY idx_paypal_order (paypal_order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLE 9: wp_bookings_working_hours
-- ============================================
CREATE TABLE wp_bookings_working_hours (
	id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	staff_id BIGINT UNSIGNED NOT NULL,
	day_of_week TINYINT UNSIGNED NOT NULL COMMENT '0=Sunday, 6=Saturday',
	start_time TIME NOT NULL,
	end_time TIME NOT NULL,
	is_active TINYINT(1) DEFAULT 1,
	created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	PRIMARY KEY (id),
	KEY idx_staff_id (staff_id),
	KEY idx_day_of_week (day_of_week),
	KEY idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLE 10: wp_bookings_settings (Key-Value Store)
-- ============================================
CREATE TABLE wp_bookings_settings (
	id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	setting_key VARCHAR(100) NOT NULL,
	setting_value LONGTEXT NULL,
	setting_type ENUM('string','integer','boolean','json') DEFAULT 'string',
	autoload TINYINT(1) DEFAULT 1 COMMENT 'Load on plugin init like wp_options',
	created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	PRIMARY KEY (id),
	UNIQUE KEY unique_setting_key (setting_key),
	KEY idx_autoload (autoload)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLE 11: wp_bookings_staff_working_hours
-- ============================================
-- Extended working hours with breaks, exceptions, and date-specific overrides.
-- Created via migration: database/migrations/migration-add-staff-working-hours.php
CREATE TABLE wp_bookings_staff_working_hours (
	id INT UNSIGNED NOT NULL AUTO_INCREMENT,
	staff_id INT UNSIGNED NOT NULL,
	day_of_week TINYINT(1) NULL COMMENT '1=Monday, 7=Sunday',
	specific_date DATE NULL COMMENT 'Exception date (vacation, etc.)',
	start_time TIME NOT NULL,
	end_time TIME NOT NULL,
	is_working TINYINT(1) DEFAULT 1 COMMENT '0=blocked/vacation',
	break_start TIME NULL,
	break_end TIME NULL,
	repeat_weekly TINYINT(1) DEFAULT 1,
	valid_from DATE NULL,
	valid_until DATE NULL,
	notes TEXT NULL,
	created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
	updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	PRIMARY KEY (id),
	KEY idx_staff_day (staff_id, day_of_week),
	KEY idx_staff_date (staff_id, specific_date),
	KEY idx_specific_date (specific_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLE 12: wp_bookings_idempotency
-- ============================================
-- Tracks idempotency keys to prevent duplicate operations
-- (Stripe checkouts, emails, webhooks). Sprint 2, Task 6.
CREATE TABLE wp_bookings_idempotency (
	id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	idempotency_key VARCHAR(255) NOT NULL,
	operation_type VARCHAR(50) NOT NULL,
	request_hash VARCHAR(64) NOT NULL,
	response_data TEXT NULL,
	status VARCHAR(20) NOT NULL DEFAULT 'processing',
	created_at DATETIME NOT NULL,
	completed_at DATETIME NULL,
	expires_at DATETIME NOT NULL,
	PRIMARY KEY (id),
	UNIQUE KEY unique_key (idempotency_key),
	KEY idx_expires (expires_at),
	KEY idx_status (status),
	KEY idx_operation_type (operation_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLE 13: wp_bookings_email_templates
-- ============================================
-- Email templates for booking notifications.
-- Created via class-bookit-activator.php on activation.
CREATE TABLE wp_bookings_email_templates (
	id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	template_key VARCHAR(50) NOT NULL,
	subject VARCHAR(255) NOT NULL,
	body TEXT NOT NULL,
	enabled TINYINT(1) DEFAULT 1,
	created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
	updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	PRIMARY KEY (id),
	UNIQUE KEY unique_template_key (template_key),
	KEY idx_template_key (template_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- MIGRATION NOTES
-- ============================================
-- Migration 1: Add Staff Photo, Bio, Title, and Custom Pricing
-- Date: 2026-01-28
-- Sprint: Sprint 1, Task 3
-- 
-- Added columns:
-- 1. wp_bookings_staff.photo_url (VARCHAR(500) NULL) - URL to uploaded staff photo
-- 2. wp_bookings_staff.bio (TEXT NULL) - Short bio shown to customers
-- 3. wp_bookings_staff.title (VARCHAR(100) NULL) - Job title (e.g., Senior Stylist)
-- 4. wp_bookings_staff_services.custom_price (DECIMAL(10,2) NULL) - Staff-specific price override
--
-- Migration file: database/migrations/migration-add-staff-fields.php
--
-- Migration 2: Add Staff Working Hours Table
-- Date: 2026-01-29
-- Sprint: Sprint 1, Task 5
--
-- Added table: wp_bookings_staff_working_hours
-- Migration file: database/migrations/migration-add-staff-working-hours.php
--
-- Migration 3: Add setting_type to Settings & Email Templates Table
-- Sprint: Sprint 2
--
-- Added column: wp_bookings_settings.setting_type ENUM('string','integer','boolean','json')
-- Added table: wp_bookings_email_templates
-- Source: includes/class-bookit-activator.php
--
-- Migration 4: Add Idempotency Table
-- Sprint: Sprint 2, Task 6
--
-- Added table: wp_bookings_idempotency
-- Source: includes/class-bookit-database.php
