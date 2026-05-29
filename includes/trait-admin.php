<?php

if (!defined('ABSPATH')) {
    exit;
}

trait Rooster_Planner_Admin
{
    public function register_post_type(): void
    {
        $labels = [
            'name' => 'Roosterinzendingen',
            'singular_name' => 'Roosterinzending',
            'menu_name' => 'Inzendingen',
        ];

        register_post_type('rooster_submission', [
            'labels' => $labels,
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => 'rooster',
            'supports' => ['title'],
            'capability_type' => 'post',
            'map_meta_cap' => true,
        ]);
    }

    public function register_admin_menu(): void
    {
        add_menu_page(
            'Rooster',
            'Rooster',
            'manage_options',
            'rooster',
            [$this, 'render_settings_page'],
            'dashicons-calendar-alt',
            58
        );

        add_submenu_page(
            'rooster',
            'Instellingen',
            'Instellingen',
            'manage_options',
            'rooster',
            [$this, 'render_settings_page']
        );

        add_submenu_page(
            'rooster',
            'Standaard roosterindeling',
            'Standaard roosterindeling',
            'manage_options',
            'rooster-default',
            [$this, 'render_default_schedule_page']
        );

        add_submenu_page(
            'rooster',
            'Afwijkende dagen',
            'Afwijkende dagen',
            'manage_options',
            'rooster-exceptions',
            [$this, 'render_exceptions_page']
        );
    }

    public function register_meta_boxes(): void
    {
        add_meta_box(
            'rooster_form_page',
            'Rooster pagina-instellingen',
            [$this, 'render_form_page_metabox'],
            'page',
            'side'
        );
    }

    public function render_form_page_metabox(\WP_Post $post): void
    {
        $is_login_page = (bool) get_post_meta($post->ID, self::LOGIN_PAGE_META, true); // Nieuw
        $is_form_page = (bool) get_post_meta($post->ID, self::FORM_PAGE_META, true);
        $is_overview_page = (bool) get_post_meta($post->ID, self::OVERVIEW_PAGE_META, true);
        wp_nonce_field('rooster_form_page_meta', 'rooster_form_page_nonce');
        ?>
        <label>
            <input type="checkbox" name="rooster_login_page" value="1" <?php checked($is_login_page); ?> />
            Dit is de roosterinlogpagina
        </label>
        <br />
        <label>
            <input type="checkbox" name="rooster_form_page" value="1" <?php checked($is_form_page); ?> />
            Dit is de roosterformulierpagina
        </label>
        <br />
        <label>
            <input type="checkbox" name="rooster_overview_page" value="1" <?php checked($is_overview_page); ?> />
            Dit is de roosteroverzichtpagina
        </label>
        <?php
    }

    public function save_form_page_meta(int $post_id, \WP_Post $post): void
    {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!isset($_POST['rooster_form_page_nonce']) || !wp_verify_nonce($_POST['rooster_form_page_nonce'], 'rooster_form_page_meta')) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Nieuw: Opslaan van de loginpagina checkbox
        $is_login_page = isset($_POST['rooster_login_page']) && $_POST['rooster_login_page'] === '1';
        if ($is_login_page) {
            update_post_meta($post_id, self::LOGIN_PAGE_META, 1);
            $this->unset_other_login_pages($post_id);
        } else {
            delete_post_meta($post_id, self::LOGIN_PAGE_META);
        }

        $is_form_page = isset($_POST['rooster_form_page']) && $_POST['rooster_form_page'] === '1';
        if ($is_form_page) {
            update_post_meta($post_id, self::FORM_PAGE_META, 1);
            $this->unset_other_form_pages($post_id);
        } else {
            delete_post_meta($post_id, self::FORM_PAGE_META);
        }

        $is_overview_page = isset($_POST['rooster_overview_page']) && $_POST['rooster_overview_page'] === '1';
        if ($is_overview_page) {
            update_post_meta($post_id, self::OVERVIEW_PAGE_META, 1);
            $this->unset_other_overview_pages($post_id);
        } else {
            delete_post_meta($post_id, self::OVERVIEW_PAGE_META);
        }
    }

    // Nieuw: Zorgt dat er maar één inlogpagina tegelijk actief is
    private function unset_other_login_pages(int $current_post_id): void
    {
        $pages = get_posts([
            'post_type' => 'page',
            'posts_per_page' => -1,
            'post__not_in' => [$current_post_id],
            'meta_key' => self::LOGIN_PAGE_META,
            'meta_value' => 1,
            'fields' => 'ids',
        ]);

        foreach ($pages as $page_id) {
            delete_post_meta($page_id, self::LOGIN_PAGE_META);
        }
    }

    private function unset_other_form_pages(int $current_post_id): void
    {
        $pages = get_posts([
            'post_type' => 'page',
            'posts_per_page' => -1,
            'post__not_in' => [$current_post_id],
            'meta_key' => self::FORM_PAGE_META,
            'meta_value' => 1,
            'fields' => 'ids',
        ]);

        foreach ($pages as $page_id) {
            delete_post_meta($page_id, self::FORM_PAGE_META);
        }
    }

    private function unset_other_overview_pages(int $current_post_id): void
    {
        $pages = get_posts([
            'post_type' => 'page',
            'posts_per_page' => -1,
            'post__not_in' => [$current_post_id],
            'meta_key' => self::OVERVIEW_PAGE_META,
            'meta_value' => 1,
            'fields' => 'ids',
        ]);

        foreach ($pages as $page_id) {
            delete_post_meta($page_id, self::OVERVIEW_PAGE_META);
        }
    }

    public function render_settings_page(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Geen toegang.');
        }

        $allow_maybe = (bool) get_option(self::OPTION_ALLOW_MAYBE, false);
        $updated = isset($_GET['settings-updated']) && $_GET['settings-updated'] === '1';
        ?>
        <div class="wrap">
            <h1>Rooster instellingen</h1>
            <?php if ($updated) : ?>
                <div class="notice notice-success is-dismissible"><p>Instellingen opgeslagen.</p></div>
            <?php endif; ?>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('rooster_save_settings', 'rooster_settings_nonce'); ?>
                <input type="hidden" name="action" value="rooster_save_settings" />

                <h2>Algemene instellingen</h2>
                <label>
                    <input type="checkbox" name="rooster_allow_maybe" value="1" <?php checked($allow_maybe); ?> />
                    Sta "Misschien" toe bij tijdslots
                </label>

                <?php submit_button('Opslaan'); ?>
            </form>
        </div>
        <?php
    }

    public function render_default_schedule_page(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Geen toegang.');
        }

        $slots = $this->get_slots();
        $days = $this->get_days();
        $updated = isset($_GET['rooster-default-updated']) && $_GET['rooster-default-updated'] === '1';
        ?>
        <div class="wrap">
            <h1>Standaard roosterindeling</h1>
            <?php if ($updated) : ?>
                <div class="notice notice-success is-dismissible"><p>Roosterindeling opgeslagen.</p></div>
            <?php endif; ?>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('rooster_save_slots', 'rooster_slots_nonce'); ?>
                <input type="hidden" name="action" value="rooster_save_slots" />

                <p>Voeg per dag één of meerdere tijdslots toe, inclusief een naam.</p>
                <div id="rooster-slots">
                    <?php foreach ($days as $day_key => $day_label) : ?>
                        <div class="rooster-day">
                            <h2><?php echo esc_html($day_label); ?></h2>
                            <table class="widefat striped">
                                <thead>
                                    <tr>
                                        <th>Naam</th>
                                        <th>Start</th>
                                        <th>Eind</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody data-day="<?php echo esc_attr($day_key); ?>">
                                    <?php
                                    $day_slots = $slots[$day_key] ?? [];
                                    if (!$day_slots) {
                                        $day_slots = [['name' => '', 'start' => '', 'end' => '']];
                                    }
                                    foreach ($day_slots as $index => $slot) :
                                        $name = $slot['name'] ?? '';
                                        $start = $slot['start'] ?? '';
                                        $end = $slot['end'] ?? '';
                                        ?>
                                        <tr>
                                            <td><input type="text" name="slots[<?php echo esc_attr($day_key); ?>][<?php echo esc_attr((string) $index); ?>][name]" value="<?php echo esc_attr($name); ?>" placeholder="Bijv. Ochtend" /></td>
                                            <td><input type="time" name="slots[<?php echo esc_attr($day_key); ?>][<?php echo esc_attr((string) $index); ?>][start]" value="<?php echo esc_attr($start); ?>" /></td>
                                            <td><input type="time" name="slots[<?php echo esc_attr($day_key); ?>][<?php echo esc_attr((string) $index); ?>][end]" value="<?php echo esc_attr($end); ?>" /></td>
                                            <td><button type="button" class="button rooster-remove-slot">Verwijderen</button></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <p><button type="button" class="button rooster-add-slot" data-day="<?php echo esc_attr($day_key); ?>">Slot toevoegen</button></p>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php submit_button('Opslaan'); ?>
            </form>
        </div>
        <script>
            (function () {
                function addSlotRow(dayKey) {
                    var body = document.querySelector('tbody[data-day="' + dayKey + '"]');
                    if (!body) {
                        return;
                    }
                    var index = body.querySelectorAll('tr').length;
                    var row = document.createElement('tr');
                    row.innerHTML =
                        '<td><input type="text" name="slots[' + dayKey + '][' + index + '][name]" value="" placeholder="Bijv. Ochtend" /></td>' +
                        '<td><input type="time" name="slots[' + dayKey + '][' + index + '][start]" value="" /></td>' +
                        '<td><input type="time" name="slots[' + dayKey + '][' + index + '][end]" value="" /></td>' +
                        '<td><button type="button" class="button rooster-remove-slot">Verwijderen</button></td>';
                    body.appendChild(row);
                }

                document.querySelectorAll('.rooster-add-slot').forEach(function (button) {
                    button.addEventListener('click', function () {
                        addSlotRow(this.getAttribute('data-day'));
                    });
                });

                document.addEventListener('click', function (event) {
                    if (event.target.classList.contains('rooster-remove-slot')) {
                        var row = event.target.closest('tr');
                        if (row) {
                            row.remove();
                        }
                    }
                });
            })();
        </script>
        <?php
    }

    public function render_exceptions_page(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Geen toegang.');
        }

        $exceptions = $this->get_exceptions();
        if ($exceptions) {
            usort($exceptions, function (array $left, array $right): int {
                return strcmp($left['date'] ?? '', $right['date'] ?? '');
            });
        }
        $updated = isset($_GET['rooster-exceptions-updated']) && $_GET['rooster-exceptions-updated'] === '1';
        ?>
        <div class="wrap">
            <h1>Afwijkende dagen</h1>
            <?php if ($updated) : ?>
                <div class="notice notice-success is-dismissible"><p>Afwijkende dagen opgeslagen.</p></div>
            <?php endif; ?>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('rooster_save_exceptions', 'rooster_exceptions_nonce'); ?>
                <input type="hidden" name="action" value="rooster_save_exceptions" />

                <p>Voeg per rij een datum Dit is een datum en tijdslot toe. Meerdere rijen met dezelfde datum worden allemaal getoond. Zet "Gesloten" aan om die datum te sluiten.</p>
                <table class="widefat striped" id="rooster-exceptions-table">
                    <thead>
                        <tr>
                            <th>Datum</th>
                            <th>Naam</th>
                            <th>Start</th>
                            <th>Eind</th>
                            <th>Gesloten</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if (!$exceptions) {
                            $exceptions = [[
                                'date' => '',
                                'name' => '',
                                'start' => '',
                                'end' => '',
                                'closed' => false,
                            ]];
                        }
                        foreach ($exceptions as $index => $exception) :
                            $date = $exception['date'] ?? '';
                            $name = $exception['name'] ?? '';
                            $start = $exception['start'] ?? '';
                            $end = $exception['end'] ?? '';
                            $closed = !empty($exception['closed']);
                            ?>
                            <tr class="rooster-exception-row">
                                <td><input type="date" name="exceptions[<?php echo esc_attr((string) $index); ?>][date]" value="<?php echo esc_attr($date); ?>" /></td>
                                <td><input type="text" class="rooster-exception-name" name="exceptions[<?php echo esc_attr((string) $index); ?>][name]" value="<?php echo esc_attr($name); ?>" placeholder="Bijv. Ochtend" /></td>
                                <td><input type="time" class="rooster-exception-time" name="exceptions[<?php echo esc_attr((string) $index); ?>][start]" value="<?php echo esc_attr($start); ?>" /></td>
                                <td><input type="time" class="rooster-exception-time" name="exceptions[<?php echo esc_attr((string) $index); ?>][end]" value="<?php echo esc_attr($end); ?>" /></td>
                                <td>
                                    <label>
                                        <input type="checkbox" class="rooster-exception-closed" name="exceptions[<?php echo esc_attr((string) $index); ?>][closed]" value="1" <?php checked($closed); ?> />
                                        Gesloten
                                    </label>
                                </td>
                                <td><button type="button" class="button link-delete rooster-remove-exception-row">Verwijderen</button></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <p><button type="button" class="button button-secondary" id="rooster-add-exception-row">Rij toevoegen</button></p>



                <?php submit_button('Opslaan'); ?>
            </form>
        </div>
        <script>
            (function () {
                var table = document.getElementById('rooster-exceptions-table');
                if (!table) {
                    return;
                }
                var body = table.querySelector('tbody');
                if (!body) {
                    return;
                }
                var nextIndex = body.querySelectorAll('tr').length;

                function toggleRow(row) {
                    var closedCheckbox = row.querySelector('.rooster-exception-closed');
                    if (!closedCheckbox) {
                        return;
                    }
                    var disabled = closedCheckbox.checked;
                    row.querySelectorAll('.rooster-exception-time, .rooster-exception-name').forEach(function (input) {
                        input.disabled = disabled;
                    });
                }

                function addRow() {
                    var index = nextIndex;
                    nextIndex += 1;
                    var row = document.createElement('tr');
                    row.className = 'rooster-exception-row';
                    row.innerHTML =
                        '<td><input type="date" name="exceptions[' + index + '][date]" value="" /></td>' +
                        '<td><input type="text" class="rooster-exception-name" name="exceptions[' + index + '][name]" value="" placeholder="Bijv. Ochtend" /></td>' +
                        '<td><input type="time" class="rooster-exception-time" name="exceptions[' + index + '][start]" value="" /></td>' +
                        '<td><input type="time" class="rooster-exception-time" name="exceptions[' + index + '][end]" value="" /></td>' +
                        '<td><label><input type="checkbox" class="rooster-exception-closed" name="exceptions[' + index + '][closed]" value="1" /> Gesloten</label></td>' +
                        '<td><button type="button" class="button link-delete rooster-remove-exception-row">Verwijderen</button></td>';
                    body.appendChild(row);
                    toggleRow(row);
                }

                var addRowButton = document.getElementById('rooster-add-exception-row');
                if (addRowButton) {
                    addRowButton.addEventListener('click', function () {
                        addRow();
                    });
                }

                body.querySelectorAll('.rooster-exception-row').forEach(function (row) {
                    toggleRow(row);
                });

                document.addEventListener('click', function (event) {
                    if (event.target.classList.contains('rooster-remove-exception-row')) {
                        var row = event.target.closest('tr');
                        if (row) {
                            row.remove();
                        }
                    }
                });

                document.addEventListener('change', function (event) {
                    if (event.target.classList.contains('rooster-exception-closed')) {
                        var row = event.target.closest('tr');
                        if (row) {
                            toggleRow(row);
                        }
                    }
                });
            })();
        </script>
        <?php
    }

    public function handle_save_settings(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Geen toegang.');
        }

        if (!isset($_POST['rooster_settings_nonce']) || !wp_verify_nonce($_POST['rooster_settings_nonce'], 'rooster_save_settings')) {
            wp_die('Ongeldige aanvraag.');
        }

        // De oude 'rooster_login_page_id' optie-opslag is hier nu veilig verwijderd

        $allow_maybe = isset($_POST['rooster_allow_maybe']) && $_POST['rooster_allow_maybe'] === '1';
        update_option(self::OPTION_ALLOW_MAYBE, $allow_maybe);

        wp_safe_redirect(add_query_arg('settings-updated', '1', admin_url('admin.php?page=rooster')));
        exit;
    }

    public function handle_save_slots(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Geen toegang.');
        }

        if (!isset($_POST['rooster_slots_nonce']) || !wp_verify_nonce($_POST['rooster_slots_nonce'], 'rooster_save_slots')) {
            wp_die('Ongeldige aanvraag.');
        }

        $cleaned = [];
        $days = $this->get_days();
        $raw_slots = $_POST['slots'] ?? [];
        foreach ($days as $day_key => $label) {
            $cleaned[$day_key] = [];
            if (!isset($raw_slots[$day_key]) || !is_array($raw_slots[$day_key])) {
                continue;
            }
            foreach ($raw_slots[$day_key] as $slot) {
                $start = isset($slot['start']) ? $this->sanitize_time($slot['start']) : null;
                $end = isset($slot['end']) ? $this->sanitize_time($slot['end']) : null;
                if (!$start || !$end) {
                    continue;
                }
                if ($this->time_to_minutes($start) >= $this->time_to_minutes($end)) {
                    continue;
                }
                $name = isset($slot['name']) ? sanitize_text_field($slot['name']) : '';
                $cleaned[$day_key][] = [
                    'name' => $name,
                    'start' => $start,
                    'end' => $end,
                ];
            }
        }

        update_option(self::OPTION_SLOTS, $cleaned);

        wp_safe_redirect(add_query_arg('rooster-default-updated', '1', admin_url('admin.php?page=rooster-default')));
        exit;
    }

    public function handle_save_exceptions(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Geen toegang.');
        }

        if (!isset($_POST['rooster_exceptions_nonce']) || !wp_verify_nonce($_POST['rooster_exceptions_nonce'], 'rooster_save_exceptions')) {
            wp_die('Ongeldige aanvraag.');
        }

        $cleaned = [];
        $raw_exceptions = $_POST['exceptions'] ?? [];
        if (is_array($raw_exceptions)) {
            foreach ($raw_exceptions as $exception) {
                if (!is_array($exception) || empty($exception['date'])) {
                    continue;
                }
                $date = $this->sanitize_date($exception['date']);
                if (!$date) {
                    continue;
                }

                $closed = !empty($exception['closed']);
                if ($closed) {
                    $cleaned[] = [
                        'date' => $date,
                        'closed' => true,
                    ];
                    continue;
                }

                $start = isset($exception['start']) ? $this->sanitize_time($exception['start']) : null;
                $end = isset($exception['end']) ? $this->sanitize_time($exception['end']) : null;
                if (!$start || !$end) {
                    continue;
                }
                if ($this->time_to_minutes($start) >= $this->time_to_minutes($end)) {
                    continue;
                }
                $name = isset($exception['name']) ? sanitize_text_field($exception['name']) : '';
                $cleaned[] = [
                    'date' => $date,
                    'name' => $name,
                    'start' => $start,
                    'end' => $end,
                    'closed' => false,
                ];
            }
        }

        if ($cleaned) {
            usort($cleaned, function (array $left, array $right): int {
                return strcmp($left['date'] ?? '', $right['date'] ?? '');
            });
        }
        update_option(self::OPTION_EXCEPTIONS, $cleaned);

        wp_safe_redirect(add_query_arg('rooster-exceptions-updated', '1', admin_url('admin.php?page=rooster-exceptions')));
        exit;
    }
}