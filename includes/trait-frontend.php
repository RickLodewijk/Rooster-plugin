<?php

if (!defined('ABSPATH')) {
    exit;
}

trait Rooster_Planner_Frontend
{
    public function maybe_handle_form_submission(): void
    {
        if (!is_singular('page')) {
            return;
        }

        global $post;
        if (!$post || !$this->is_form_page($post->ID)) {
            return;
        }

        if (!is_user_logged_in()) {
            $this->redirect_to_login();
        }

        if (!$this->user_has_access()) {
            wp_die('Je hebt geen toegang tot dit formulier.', 'Geen toegang', ['response' => 403]);
        }

        if (!isset($_POST['rooster_form_submit'])) {
            return;
        }

        if (!isset($_POST['rooster_form_nonce']) || !wp_verify_nonce($_POST['rooster_form_nonce'], 'rooster_form_submit')) {
            $this->form_errors[] = 'Ongeldige beveiligingstoken.';
            return;
        }

        $week = isset($_POST['rooster_week']) ? sanitize_text_field($_POST['rooster_week']) : '';
        $allowed_weeks = array_keys($this->get_week_options());
        if (!in_array($week, $allowed_weeks, true)) {
            $this->form_errors[] = 'Kies een geldige week.';
        }

        $allow_maybe = $this->get_allow_maybe();
        $available_slot_ids = $this->get_available_slot_ids($week);
        $selected_slots = [];
        $selected_maybe = [];

        if ($allow_maybe && isset($_POST['rooster_slot_choice']) && is_array($_POST['rooster_slot_choice'])) {
            foreach ($_POST['rooster_slot_choice'] as $slot_id => $choice) {
                $slot_id = sanitize_text_field((string) $slot_id);
                $choice = sanitize_text_field((string) $choice);
                if (!in_array($slot_id, $available_slot_ids, true)) {
                    continue;
                }
                if ($choice === 'yes') {
                    $selected_slots[] = $slot_id;
                } elseif ($choice === 'maybe') {
                    $selected_maybe[] = $slot_id;
                }
            }
        } else {
            if (isset($_POST['rooster_slots']) && is_array($_POST['rooster_slots'])) {
                foreach ($_POST['rooster_slots'] as $slot_id) {
                    $slot_id = sanitize_text_field($slot_id);
                    if (in_array($slot_id, $available_slot_ids, true)) {
                        $selected_slots[] = $slot_id;
                    }
                }
            }
            if ($allow_maybe && isset($_POST['rooster_slots_maybe']) && is_array($_POST['rooster_slots_maybe'])) {
                foreach ($_POST['rooster_slots_maybe'] as $slot_id) {
                    $slot_id = sanitize_text_field($slot_id);
                    if (in_array($slot_id, $available_slot_ids, true)) {
                        $selected_maybe[] = $slot_id;
                    }
                }
            }
            $selected_maybe = array_values(array_diff($selected_maybe, $selected_slots));
        }

        if ($this->form_errors) {
            return;
        }

        $user_id = get_current_user_id();
        $post_id = $this->get_existing_submission_id($user_id, $week);
        $title = sprintf('Week %s - %s', $week, wp_get_current_user()->display_name);

        if ($post_id) {
            wp_update_post([
                'ID' => $post_id,
                'post_title' => $title,
            ]);
        } else {
            $post_id = wp_insert_post([
                'post_type' => 'rooster_submission',
                'post_status' => 'publish',
                'post_title' => $title,
                'post_author' => $user_id,
            ]);
        }

        if (is_wp_error($post_id) || !$post_id) {
            $this->form_errors[] = 'Opslaan mislukt. Probeer het opnieuw.';
            return;
        }

        update_post_meta($post_id, 'rooster_week', $week);
        update_post_meta($post_id, 'rooster_slots', $selected_slots);
        if ($allow_maybe) {
            update_post_meta($post_id, 'rooster_slots_maybe', $selected_maybe);
        } else {
            delete_post_meta($post_id, 'rooster_slots_maybe');
        }
        delete_post_meta($post_id, 'rooster_note');

        wp_safe_redirect(add_query_arg([
            'rooster_saved' => '1',
            'rooster_week' => $week,
        ], get_permalink($post)));
        exit;
    }

    public function maybe_append_form(string $content): string
    {
        if (!is_singular('page')) {
            return $content;
        }

        global $post;
        if (!$post || !$this->is_form_page($post->ID)) {
            return $content;
        }

        if (!is_user_logged_in()) {
            $login_url = $this->get_login_url(get_permalink($post));
            return '<p><a href="' . esc_url($login_url) . '">Inloggen om het formulier te openen</a></p>';
        }

        if (!$this->user_has_access()) {
            return '<p>Je hebt geen toegang tot dit formulier.</p>';
        }

        return $this->render_form();
    }

    public function maybe_append_overview(string $content): string
    {
        if (!is_singular('page')) {
            return $content;
        }

        global $post;
        if (!$post || !$this->is_overview_page($post->ID)) {
            return $content;
        }

        return $this->render_overview_shortcode();
    }

    public function render_login_shortcode(): string
    {
        if (is_user_logged_in()) {
            $target = $this->get_form_page_url() ?: home_url('/');
            return '<p>Je bent al ingelogd. <a href="' . esc_url($target) . '">Ga naar het formulier</a>.</p>';
        }

        $redirect = isset($_GET['redirect_to']) ? esc_url_raw($_GET['redirect_to']) : ($this->get_form_page_url() ?: home_url('/'));

        ob_start();
        wp_login_form([
            'redirect' => $redirect,
            'label_username' => 'Gebruikersnaam of e-mail',
            'label_password' => 'Wachtwoord',
            'label_remember' => 'Onthouden',
            'label_log_in' => 'Inloggen',
        ]);
        return ob_get_clean();
    }

    public function render_overview_shortcode(): string
    {
        if (!is_user_logged_in()) {
            $login_url = $this->get_login_url(get_permalink());
            return '<p><a href="' . esc_url($login_url) . '">Inloggen om het overzicht te bekijken</a></p>';
        }

        if (!$this->user_has_access()) {
            return '<p>Je hebt geen toegang tot dit overzicht.</p>';
        }

        $week_options = $this->get_week_options();
        $selected_week = $this->get_selected_week(array_keys($week_options));
        $days = $this->get_days();
        $day_keys = array_keys($days);
        
        // HIER TOEGEVOEGD: Haal de datums op voor de geselecteerde week
        $week_dates = $this->get_week_dates($selected_week);
        
        $slot_label_map = $this->get_slot_time_label_map($selected_week);
        $allow_maybe = $this->get_allow_maybe();

        $query = new \WP_Query([
            'post_type' => 'rooster_submission',
            'posts_per_page' => 200,
            'orderby' => 'modified',
            'order' => 'DESC',
            'meta_key' => 'rooster_week',
            'meta_value' => $selected_week,
        ]);

        $totals = array_fill_keys($day_keys, 0);
        $rows_html = '';

        $has_posts = $query->have_posts();
        if ($has_posts) {
            while ($query->have_posts()) {
                $query->the_post();
                $post_id = get_the_ID();
                $user = get_userdata((int) get_post_field('post_author', $post_id));
                $slots = (array) get_post_meta($post_id, 'rooster_slots', true);
                $slots_by_day = $this->group_slots_by_day($slots, $slot_label_map, $day_keys);
                $maybe_slots = $allow_maybe ? (array) get_post_meta($post_id, 'rooster_slots_maybe', true) : [];
                $maybe_by_day = $allow_maybe ? $this->group_slots_by_day($maybe_slots, $slot_label_map, $day_keys) : array_fill_keys($day_keys, []);

                $rows_html .= '<tr>';
                $rows_html .= '<td class="naam-kolom">' . esc_html($user ? $user->display_name : 'Onbekend') . '</td>';
                foreach ($day_keys as $day_key) {
                    $labels = $slots_by_day[$day_key] ?? [];
                    $maybe_labels = $maybe_by_day[$day_key] ?? [];
                    if (!$labels && !$maybe_labels) {
                        $rows_html .= '<td class="afwezig">Afwezig</td>';
                        continue;
                    }
                    $badges = '';
                    foreach ($labels as $label) {
                        $badges .= '<span class="tijd-badge">' . esc_html($label) . '</span> ';
                    }
                    foreach ($maybe_labels as $label) {
                        $badges .= '<span class="tijd-badge maybe">Misschien: ' . esc_html($label) . '</span> ';
                    }
                    if ($labels) {
                        $totals[$day_key] += 1;
                    }
                    $rows_html .= '<td>' . trim($badges) . '</td>';
                }
                $rows_html .= '</tr>';
            }
            wp_reset_postdata();
        } else {
            $rows_html .= '<tr><td class="afwezig" colspan="8">Er zijn nog geen inzendingen voor deze week.</td></tr>';
        }

        $totals_row = '';
        if ($has_posts) {
            $totals_row .= '<tr class="totaal-rij">';
            $totals_row .= '<td class="naam-kolom">Totaal</td>';
            foreach ($day_keys as $day_key) {
                $count = $totals[$day_key] ?? 0;
                $totals_row .= '<td><span class="totaal">' . esc_html($count . ' mensen') . '</span></td>';
            }
            $totals_row .= '</tr>';
        }

        ob_start();
        ?>
        <style>
            .rooster-overview {
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                background-color: #ffffff;
                color: #333;
                padding: 0;
                display: block;
                width: 100%;
                max-width: 100%;
                margin-left: calc(50% - 50vw);
                margin-right: calc(50% - 50vw);
                box-sizing: border-box;
                overflow-x: hidden;
            }
            .rooster-overview .container {
                width: 100%;
                max-width: none;
                background-color: #ffffff;
                padding: 24px;
                border-radius: 0;
                box-shadow: none;
            }
            .rooster-overview h1 {
                color: #2c3e50;
                margin-top: 0;
                margin-bottom: 25px;
                font-size: 24px;
            }
            .rooster-overview .selector-container {
                margin-bottom: 25px;
                display: flex;
                align-items: center;
                gap: 12px;
            }
            .rooster-overview label {
                font-weight: 600;
                color: #4a5568;
            }
            .rooster-overview select {
                padding: 10px 15px;
                font-size: 16px;
                border: 2px solid #cbd5e1;
                border-radius: 6px;
                background-color: #fff;
                color: #1e293b;
                cursor: pointer;
                outline: none;
                transition: border-color 0.2s;
            }
            .rooster-overview select:focus {
                border-color: #3b82f6;
            }
            .rooster-overview .table-responsive {
                overflow: visible;
            }
            .rooster-overview table {
                width: 100%;
                border-collapse: collapse;
                margin-top: 10px;
                text-align: left;
                table-layout: fixed;
            }
            .rooster-overview th,
            .rooster-overview td {
                padding: 14px 16px;
                border-bottom: 1px solid #e2e8f0;
                vertical-align: middle;
                word-break: break-word;
            }
            .rooster-overview th {
                background-color: #f8fafc;
                color: #475569;
                font-weight: 600;
                text-transform: uppercase;
                font-size: 13px;
                letter-spacing: 0.5px;
            }
            .rooster-overview tr:hover {
                background-color: #f8fafc;
            }
            .rooster-overview .naam-kolom {
                font-weight: 600;
                color: #1e293b;
                background-color: #fff;
                border-right: 2px solid #e2e8f0;
                font-size: 15px;
                white-space: normal;
            }
            .rooster-overview .tijd-badge {
                display: inline-block;
                padding: 6px 10px;
                border-radius: 4px;
                font-size: 13px;
                font-weight: 500;
                background-color: #e0f2fe;
                color: #0369a1;
                border: 1px solid #bae6fd;
                margin: 2px 6px 2px 0;
            }
            .rooster-overview .tijd-badge.maybe {
                background-color: #fff7ed;
                color: #c2410c;
                border-color: #fed7aa;
            }
            .rooster-overview .afwezig {
                color: #94a3b8;
                font-size: 14px;
                font-style: italic;
            }
            .rooster-overview .totaal {
                font-weight: 600;
                color: #1e293b;
                font-size: 14px;
            }
            @media (max-width: 1024px) {
                .rooster-overview .container {
                    padding: 20px;
                }
                .rooster-overview h1 {
                    font-size: 20px;
                }
                .rooster-overview th,
                .rooster-overview td {
                    padding: 10px 12px;
                    font-size: 13px;
                }
                .rooster-overview .tijd-badge {
                    font-size: 12px;
                    padding: 5px 8px;
                }
                .rooster-overview .naam-kolom {
                    font-size: 14px;
                }
            }
        </style>
        <div class="rooster-overview">
            <div class="container">
                <h1>Aanwezigheidsrooster</h1>
                <div class="selector-container">
                    <label for="rooster-week-select">Selecteer week:</label>
                    <select id="rooster-week-select" name="rooster_week">
                        <?php foreach ($week_options as $value => $label) : ?>
                            <option value="<?php echo esc_attr($value); ?>" <?php selected($selected_week, $value); ?>>
                                <?php echo esc_html($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Naam</th>
                                <?php foreach ($days as $day_key => $day_label) : ?>
                                    <th>
                                        <?php echo esc_html($day_label); ?>
                                        <?php if (isset($week_dates[$day_key])) : ?>
                                            <br />
                                            <small style="font-weight: normal; font-size: 11px; color: #64748b;">
                                                <?php echo date_i18n('d-m-Y', strtotime($week_dates[$day_key])); ?>
                                            </small>
                                        <?php endif; ?>
                                    </th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody id="rooster-body">
                            <?php echo $rows_html; ?>
                            <?php echo $totals_row; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <script>
            (function () {
                var select = document.getElementById('rooster-week-select');
                if (!select) {
                    return;
                }
                select.addEventListener('change', function () {
                    var url = new URL(window.location.href);
                    url.searchParams.set('rooster_week', this.value);
                    window.location = url.toString();
                });
            })();
        </script>
        <?php
        return ob_get_clean();
    }

    private function group_slots_by_day(array $slot_ids, array $slot_label_map, array $day_keys): array
    {
        $grouped = array_fill_keys($day_keys, []);
        foreach ($slot_ids as $slot_id) {
            if (!is_string($slot_id) || !str_contains($slot_id, '|')) {
                continue;
            }
            [$day_key, $time] = explode('|', $slot_id, 2);
            if (!array_key_exists($day_key, $grouped)) {
                continue;
            }
            $label = $slot_label_map[$slot_id] ?? str_replace('-', '–', $time);
            $grouped[$day_key][] = $label;
        }
        return $grouped;
    }

    private function render_form(): string
    {
        $week_options = $this->get_week_options();
        $selected_week = $this->get_selected_week(array_keys($week_options));
        $submission = $this->get_user_submission(get_current_user_id(), $selected_week);
        $selected_slots = $submission['slots'];
        $selected_maybe = $this->get_allow_maybe() ? $submission['maybe'] : [];

        $notice = '';
        if (isset($_GET['rooster_saved']) && $_GET['rooster_saved'] === '1') {
            $notice = '<div class="notice notice-success"><p>Inzending opgeslagen.</p></div>';
        } elseif ($this->form_errors) {
            $errors = array_map('esc_html', $this->form_errors);
            $notice = '<div class="notice notice-error"><ul><li>' . implode('</li><li>', $errors) . '</li></ul></div>';
        }

        $slots_markup = $this->render_slots_inputs($selected_week, $selected_slots, $selected_maybe);

        ob_start();
        ?>
        <?php echo $notice; ?>
        <form method="post" id="rooster-form">
            <?php wp_nonce_field('rooster_form_submit', 'rooster_form_nonce'); ?>
            <p>
                <label for="rooster_week"><strong>Week</strong></label><br />
                <select id="rooster_week" name="rooster_week">
                    <?php foreach ($week_options as $value => $label) : ?>
                        <option value="<?php echo esc_attr($value); ?>" <?php selected($selected_week, $value); ?>>
                            <?php echo esc_html($label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </p>

            <?php echo $slots_markup; ?>

            <p>
                <button type="submit" name="rooster_form_submit" class="button button-primary">Opslaan</button>
            </p>
        </form>
        <script>
            (function () {
                var select = document.getElementById('rooster_week');
                if (!select) {
                    return;
                }
                select.addEventListener('change', function () {
                    var url = new URL(window.location.href);
                    url.searchParams.set('rooster_week', this.value);
                    url.searchParams.delete('rooster_saved');
                    window.location = url.toString();
                });
            })();
        </script>
        <?php
        return ob_get_clean();
    }

    private function render_slots_inputs(string $week, array $selected_slots, array $selected_maybe): string
    {
        $slots_by_day = $this->get_slots_for_week($week);
        $days = $this->get_days();
        $week_dates = $this->get_week_dates($week);
        $allow_maybe = $this->get_allow_maybe();

        if (!$this->has_any_slots($slots_by_day)) {
            return '<p>Er zijn nog geen tijdslots ingesteld. Vraag de beheerder om dit in te stellen.</p>';
        }

        $markup = '';
        foreach ($days as $day_key => $day_label) {
            if (empty($slots_by_day[$day_key])) {
                continue;
            }
            $date_label = '';
            if (isset($week_dates[$day_key])) {
                $date_label = ' (' . date_i18n('d-m-Y', strtotime($week_dates[$day_key])) . ')';
            }
            $markup .= '<fieldset><legend><strong>' . esc_html($day_label . $date_label) . '</strong></legend>';
            foreach ($slots_by_day[$day_key] as $slot) {
                $slot_id = $this->build_slot_id($day_key, $slot['start'], $slot['end']);
                $time_label = $slot['start'] . '–' . $slot['end'];
                $name = isset($slot['name']) ? trim((string) $slot['name']) : '';
                $label = $name ? $name . ' (' . $time_label . ')' : $time_label;
                $checked = in_array($slot_id, $selected_slots, true);
                $checked_maybe = in_array($slot_id, $selected_maybe, true);
                $markup .= '<div style="margin:10px 0;">';
                $markup .= '<div style="font-weight:600;">' . esc_html($label) . '</div>';
                if ($allow_maybe) {
                    $name = 'rooster_slot_choice[' . esc_attr($slot_id) . ']';
                    $markup .= '<div style="margin-top:4px;">';
                    $markup .= '<label style="display:block;margin:2px 0;">';
                    $markup .= '<input type="radio" name="' . $name . '" value="yes" ' . checked($checked, true, false) . ' /> Ja';
                    $markup .= '</label>';
                    $markup .= '<label style="display:block;margin:2px 0;">';
                    $markup .= '<input type="radio" name="' . $name . '" value="maybe" ' . checked($checked_maybe, true, false) . ' /> Misschien';
                    $markup .= '</label>';
                    $markup .= '<label style="display:block;margin:2px 0;">';
                    $markup .= '<input type="radio" name="' . $name . '" value="absent" ' . checked(!$checked && !$checked_maybe, true, false) . ' /> Afwezig';
                    $markup .= '</label>';
                    $markup .= '</div>';
                } else {
                    $markup .= '<div style="margin-top:4px;">';
                    $markup .= '<label style="display:block;margin:2px 0;">';
                    $markup .= '<input type="checkbox" name="rooster_slots[]" value="' . esc_attr($slot_id) . '" ' . checked($checked, true, false) . ' /> Ja';
                    $markup .= '</label>';
                    $markup .= '</div>';
                }
                $markup .= '</div>';
            }
            $markup .= '</fieldset>';
        }

        return $markup;
    }

    private function user_has_access(): bool
    {
        return current_user_can(self::CAPABILITY) || current_user_can('manage_options');
    }

    private function redirect_to_login(): void
    {
        $redirect = $this->get_form_page_url() ?: home_url('/');
        wp_safe_redirect($this->get_login_url($redirect));
        exit;
    }

    private function get_login_url(string $redirect_to): string
    {
        $login_page_id = (int) get_option(self::OPTION_LOGIN_PAGE_ID, 0);
        if ($login_page_id) {
            return add_query_arg('redirect_to', rawurlencode($redirect_to), get_permalink($login_page_id));
        }

        return wp_login_url($redirect_to);
    }

    private function get_form_page_url(): ?string
    {
        $page = $this->get_form_page();
        return $page ? get_permalink($page) : null;
    }

    private function get_overview_page_url(): ?string
    {
        $page = $this->get_overview_page();
        return $page ? get_permalink($page) : null;
    }

    private function get_form_page(): ?\WP_Post
    {
        $pages = get_posts([
            'post_type' => 'page',
            'posts_per_page' => 1,
            'meta_key' => self::FORM_PAGE_META,
            'meta_value' => 1,
        ]);

        return $pages ? $pages[0] : null;
    }

    private function get_overview_page(): ?\WP_Post
    {
        $pages = get_posts([
            'post_type' => 'page',
            'posts_per_page' => 1,
            'meta_key' => self::OVERVIEW_PAGE_META,
            'meta_value' => 1,
        ]);

        return $pages ? $pages[0] : null;
    }

    private function is_form_page(int $post_id): bool
    {
        return (bool) get_post_meta($post_id, self::FORM_PAGE_META, true);
    }

    private function is_overview_page(int $post_id): bool
    {
        return (bool) get_post_meta($post_id, self::OVERVIEW_PAGE_META, true);
    }
}