<?php
/**
 * Admin menu tests.
 *
 * @package Booking_System
 */

use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Test admin menu registration.
 */
class Test_Admin_Menu extends TestCase {

	/**
	 * Set up test.
	 */
	public function setUp(): void {
		parent::setUp();
		require_once BOOKING_SYSTEM_PATH . 'admin/class-booking-admin-menu.php';
	}

	/**
	 * Test admin menu class exists.
	 */
	public function test_admin_menu_class_exists() {
		$this->assertTrue( class_exists( 'Booking_Admin_Menu' ) );
	}

	/**
	 * Test admin menu can be instantiated.
	 */
	public function test_admin_menu_instantiation() {
		$admin_menu = new Booking_Admin_Menu();
		$this->assertInstanceOf( 'Booking_Admin_Menu', $admin_menu );
	}

	/**
	 * Test admin menu registration.
	 */
	public function test_admin_menu_registration() {
		global $menu, $submenu;
		
		// Clear existing menus
		$menu    = array();
		$submenu = array();
		
		$admin_menu = new Booking_Admin_Menu();
		$admin_menu->register_menu();
		
		// Check main menu is registered
		$found_main_menu = false;
		foreach ( $menu as $menu_item ) {
			if ( isset( $menu_item[2] ) && $menu_item[2] === 'booking-system' ) {
				$found_main_menu = true;
				break;
			}
		}
		
		$this->assertTrue( $found_main_menu, 'Main booking system menu should be registered' );
		
		// Check submenus are registered
		$this->assertArrayHasKey( 'booking-system', $submenu );
		
		$submenu_slugs = array();
		if ( isset( $submenu['booking-system'] ) ) {
			foreach ( $submenu['booking-system'] as $submenu_item ) {
				$submenu_slugs[] = $submenu_item[2];
			}
		}
		
		// Check for key submenu pages
		$expected_submenus = array(
			'booking-system',
			'booking-calendar',
			'booking-add-new',
			'booking-services',
			'booking-service-categories',
			'booking-add-service',
			'booking-staff',
			'booking-add-staff',
			'booking-customers',
			'booking-export-customers',
			'booking-settings',
		);
		
		foreach ( $expected_submenus as $expected_slug ) {
			$this->assertContains(
				$expected_slug,
				$submenu_slugs,
				"Submenu $expected_slug should be registered"
			);
		}
	}

	/**
	 * Test admin menu has correct capability.
	 */
	public function test_admin_menu_capability() {
		global $menu, $submenu;
		
		// Clear existing menus
		$menu    = array();
		$submenu = array();
		
		$admin_menu = new Booking_Admin_Menu();
		$admin_menu->register_menu();
		
		// Check main menu capability
		$main_menu_capability = null;
		foreach ( $menu as $menu_item ) {
			if ( isset( $menu_item[2] ) && $menu_item[2] === 'booking-system' ) {
				$main_menu_capability = $menu_item[1];
				break;
			}
		}
		
		$this->assertEquals( 'manage_options', $main_menu_capability );
		
		// Check submenu capabilities
		if ( isset( $submenu['booking-system'] ) ) {
			foreach ( $submenu['booking-system'] as $submenu_item ) {
				$this->assertEquals(
					'manage_options',
					$submenu_item[1],
					"Submenu {$submenu_item[2]} should have manage_options capability"
				);
			}
		}
	}
}
