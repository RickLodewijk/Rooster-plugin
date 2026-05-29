<?php
/**
 * Plugin Name: Rooster Planner
 * Description: Maakt roosterinvoer mogelijk met een afgeschermde formulierpagina en beheerinstellingen.
 * Version: 0.1.0
 * Author: Rick Lodewijk
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/includes/trait-admin.php';
require_once __DIR__ . '/includes/trait-frontend.php';
require_once __DIR__ . '/includes/trait-data.php';

class Rooster_Planner
{
    use Rooster_Planner_Admin;
    use Rooster_Planner_Frontend;
    use Rooster_Planner_Data;

    private const ROLE_KEY = 'medewerker_rooster';
    private const ROLE_LABEL = 'Medewerker rooster';
    private const CAPABILITY = 'rooster_access';
    private const LOGIN_PAGE_META = '_rooster_login_page';
    private const FORM_PAGE_META = '_rooster_form_page';
    private const OVERVIEW_PAGE_META = '_rooster_overview_page';
    private const OPTION_SLOTS = 'rooster_slots';
    private const OPTION_ALLOW_MAYBE = 'rooster_allow_maybe';
    private const OPTION_EXCEPTIONS = 'rooster_exceptions';
    private const WEEKS_AHEAD = 4;

    private array $form_errors = [];

    public static function activate(): void
    {
        // 1. Voeg de gebruikersrol toe
        add_role(
            self::ROLE_KEY,
            self::ROLE_LABEL,
            [
                'read' => true,
                self::CAPABILITY => true,
            ]
        );

        // 2. Maak de benodigde pagina's automatisch aan
        self::create_default_pages();
    }

    private static function create_default_pages(): void
    {
        // Definieer de pagina's die aangemaakt moeten worden
        $pages = [
            'login' => [
                'title'   => 'Rooster Inloggen',
                'content' => '[rooster_login]',
                'option'  => null,
                'meta'    => self::LOGIN_PAGE_META
            ],
            'form' => [
                'title'   => 'Rooster Formulier',
                'content' => 'Hieronder kunt u uw rooster invullen.',
                'option'  => null,
                'meta'    => self::FORM_PAGE_META
            ],
            'overview' => [
                'title'   => 'Rooster Overzicht',
                'content' => '[rooster_overview]',
                'option'  => null,
                'meta'    => self::OVERVIEW_PAGE_META
            ]
        ];

        foreach ($pages as $key => $page_data) {
            $page_exists = false;

            // Check via optie (indien gebruikt)
            if ($page_data['option']) {
                $existing_id = get_option($page_data['option']);
                if ($existing_id && get_post($existing_id)) {
                    $page_exists = true;
                }
            }

            // Check via meta data (voor alle pagina's, inclusief login)
            if (!$page_exists && $page_data['meta']) {
                $existing_pages = get_posts([
                    'post_type'      => 'page',
                    'meta_key'       => $page_data['meta'],
                    'meta_value'     => '1',
                    'posts_per_page' => 1,
                    'post_status'    => 'any'
                ]);
                if (!empty($existing_pages)) {
                    $page_exists = true;
                }
            }

            // Als de pagina nog niet bestaat, maak hem aan
            if (!$page_exists) {
                $page_id = wp_insert_post([
                    'post_title'   => $page_data['title'],
                    'post_content' => $page_data['content'],
                    'post_status'  => 'publish',
                    'post_type'    => 'page',
                ]);

                if (!is_wp_error($page_id)) {
                    // Sla de ID op in de opties indien ingesteld
                    if ($page_data['option']) {
                        update_option($page_data['option'], $page_id);
                    }
                    // Sla de meta-waarde op
                    if ($page_data['meta']) {
                        update_post_meta($page_id, $page_data['meta'], '1');
                    }
                }
            }
        }
    }

    public static function init(): void
    {
        new self();
    }

    public function __construct()
    {
        add_action('init', [$this, 'register_post_type']);
        add_action('admin_menu', [$this, 'register_admin_menu']);
        add_action('admin_post_rooster_save_settings', [$this, 'handle_save_settings']);
        add_action('admin_post_rooster_save_slots', [$this, 'handle_save_slots']);
        add_action('admin_post_rooster_save_exceptions', [$this, 'handle_save_exceptions']);
        add_action('add_meta_boxes', [$this, 'register_meta_boxes']);
        add_action('save_post_page', [$this, 'save_form_page_meta'], 10, 2);
        
        // Toegevoegd aan template_redirect voor de beveiliging en formulierafhandeling
        add_action('template_redirect', [$this, 'restrict_rooster_pages']);
        add_action('template_redirect', [$this, 'maybe_handle_form_submission']);
        
        add_filter('the_content', [$this, 'maybe_append_form']);
        add_filter('the_content', [$this, 'maybe_append_overview']);
        add_shortcode('rooster_login', [$this, 'render_login_shortcode']);
        add_shortcode('rooster_overview', [$this, 'render_overview_shortcode']);
    }

    /**
     * Stuurt niet-ingelogde gebruikers door naar de homepage als ze de formulier- of overzichtspagina bezoeken.
     */
    public function restrict_rooster_pages(): void
    {
        // We hoeven alleen te controleren als de gebruiker NIET is ingelogd
        if (!is_user_logged_in()) {
            
            // Controleer of we ons op een WordPress pagina bevinden
            if (is_page()) {
                $page_id = get_the_ID();
                
                // Haal de meta-waarden op om te kijken of dit de afgeschermde pagina's zijn
                $is_form_page     = get_post_meta($page_id, self::FORM_PAGE_META, true);
                $is_overview_page = get_post_meta($page_id, self::OVERVIEW_PAGE_META, true);

                // Als het een van deze pagina's is, stuur ze door naar de homepage
                if ($is_form_page === '1' || $is_overview_page === '1') {
                    wp_safe_redirect(home_url('/'));
                    exit;
                }
            }
        }
    }
}

register_activation_hook(__FILE__, ['Rooster_Planner', 'activate']);
Rooster_Planner::init();