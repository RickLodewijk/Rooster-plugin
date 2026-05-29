<?php

if (!defined('ABSPATH')) {
    exit;
}

trait Rooster_Planner_Data
{
    private function get_days(): array
    {
        return [
            'monday' => 'Maandag',
            'tuesday' => 'Dinsdag',
            'wednesday' => 'Woensdag',
            'thursday' => 'Donderdag',
            'friday' => 'Vrijdag',
            'saturday' => 'Zaterdag',
            'sunday' => 'Zondag',
        ];
    }

    private function get_slots(): array
    {
        $slots = get_option(self::OPTION_SLOTS, []);
        return is_array($slots) ? $slots : [];
    }

    private function get_allow_maybe(): bool
    {
        return (bool) get_option(self::OPTION_ALLOW_MAYBE, false);
    }

    private function get_exceptions(): array
    {
        $exceptions = get_option(self::OPTION_EXCEPTIONS, []);
        if (!is_array($exceptions) || !$exceptions) {
            return [];
        }

        $first_key = array_key_first($exceptions);
        $first_value = $exceptions[$first_key] ?? null;

        if (is_int($first_key)) {
            return $exceptions;
        }

        if (is_string($first_key) && is_array($first_value) && array_key_exists('slots', $first_value)) {
            $rows = [];
            foreach ($exceptions as $date => $data) {
                if (!is_string($date)) {
                    continue;
                }
                $slots = $data['slots'] ?? [];
                if (!$slots) {
                    $rows[] = [
                        'date' => $date,
                        'closed' => true,
                    ];
                    continue;
                }
                foreach ($slots as $slot) {
                    if (!is_array($slot)) {
                        continue;
                    }
                    $rows[] = [
                        'date' => $date,
                        'name' => $slot['name'] ?? '',
                        'start' => $slot['start'] ?? '',
                        'end' => $slot['end'] ?? '',
                        'closed' => false,
                    ];
                }
            }
            return $rows;
        }

        return [];
    }

    private function get_slots_for_week(string $week): array
    {
        $slots_by_day = $this->get_slots();
        $exceptions_by_date = $this->get_exceptions_by_date();
        $week_dates = $this->get_week_dates($week);

        foreach ($week_dates as $day_key => $date) {
            if (isset($exceptions_by_date[$date])) {
                $exception = $exceptions_by_date[$date];
                $slots_by_day[$day_key] = $exception['closed'] ? [] : $exception['slots'];
            }
        }

        return $slots_by_day;
    }

    private function get_exceptions_by_date(): array
    {
        $exceptions = $this->get_exceptions();
        $by_date = [];
        foreach ($exceptions as $exception) {
            if (!is_array($exception)) {
                continue;
            }
            $date = $exception['date'] ?? '';
            if (!$date) {
                continue;
            }
            if (!isset($by_date[$date])) {
                $by_date[$date] = [
                    'closed' => false,
                    'slots' => [],
                ];
            }
            if (!empty($exception['closed'])) {
                $by_date[$date]['closed'] = true;
                continue;
            }
            if (!isset($exception['start'], $exception['end'])) {
                continue;
            }
            $by_date[$date]['slots'][] = [
                'name' => $exception['name'] ?? '',
                'start' => $exception['start'],
                'end' => $exception['end'],
            ];
        }

        return $by_date;
    }

    private function has_any_slots(array $slots_by_day): bool
    {
        foreach ($slots_by_day as $slots) {
            if (!empty($slots)) {
                return true;
            }
        }
        return false;
    }

    private function sanitize_time(string $value): ?string
    {
        $value = trim($value);
        if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $value)) {
            return null;
        }
        return $value;
    }

    private function sanitize_date(string $value): ?string
    {
        $value = trim($value);
        $date = \DateTimeImmutable::createFromFormat('Y-m-d', $value);
        if (!$date || $date->format('Y-m-d') !== $value) {
            return null;
        }
        return $value;
    }

    private function time_to_minutes(string $time): int
    {
        [$hour, $minute] = explode(':', $time);
        return ((int) $hour * 60) + (int) $minute;
    }

    private function build_slot_id(string $day_key, string $start, string $end): string
    {
        return $day_key . '|' . $start . '-' . $end;
    }

    private function get_available_slot_ids(string $week): array
    {
        $slots_by_day = $this->get_slots_for_week($week);
        $slot_ids = [];
        foreach ($slots_by_day as $day_key => $slots) {
            foreach ($slots as $slot) {
                if (!isset($slot['start'], $slot['end'])) {
                    continue;
                }
                $slot_ids[] = $this->build_slot_id($day_key, $slot['start'], $slot['end']);
            }
        }
        return $slot_ids;
    }

    private function get_week_options(): array
    {
        $timezone = wp_timezone();
        $today = new \DateTimeImmutable('now', $timezone);
        $week_start = $today->modify('monday this week');
        $options = [];

        for ($i = 0; $i < self::WEEKS_AHEAD; $i++) {
            $start = $week_start->modify('+' . $i . ' week');
            $end = $start->modify('+6 days');
            $year = $start->format('o');
            $week = $start->format('W');
            $key = $year . '-W' . $week;
            $label = sprintf(
                'Week %s (%s t/m %s)',
                $week,
                $start->format('d-m-Y'),
                $end->format('d-m-Y')
            );
            $options[$key] = $label;
        }

        return $options;
    }

    private function get_week_dates(string $week_key): array
    {
        if (!preg_match('/^(?<year>\d{4})-W(?<week>\d{2})$/', $week_key, $matches)) {
            return [];
        }

        $timezone = wp_timezone();
        $week_start = (new \DateTimeImmutable('now', $timezone))
            ->setISODate((int) $matches['year'], (int) $matches['week']);

        $dates = [];
        $day_keys = array_keys($this->get_days());
        foreach ($day_keys as $offset => $day_key) {
            $dates[$day_key] = $week_start->modify('+' . $offset . ' days')->format('Y-m-d');
        }

        return $dates;
    }

    private function get_selected_week(array $allowed_weeks): string
    {
        if (isset($_GET['rooster_week']) && in_array($_GET['rooster_week'], $allowed_weeks, true)) {
            return sanitize_text_field($_GET['rooster_week']);
        }
        if (isset($_POST['rooster_week']) && in_array($_POST['rooster_week'], $allowed_weeks, true)) {
            return sanitize_text_field($_POST['rooster_week']);
        }

        return $allowed_weeks[0] ?? '';
    }

    private function get_existing_submission_id(int $user_id, string $week): ?int
    {
        $query = new \WP_Query([
            'post_type' => 'rooster_submission',
            'post_status' => 'publish',
            'posts_per_page' => 1,
            'author' => $user_id,
            'meta_key' => 'rooster_week',
            'meta_value' => $week,
            'fields' => 'ids',
        ]);

        if ($query->have_posts()) {
            return (int) $query->posts[0];
        }

        return null;
    }

    private function get_user_submission(int $user_id, string $week): array
    {
        $post_id = $this->get_existing_submission_id($user_id, $week);
        if (!$post_id) {
            return ['slots' => [], 'maybe' => []];
        }

        return [
            'slots' => (array) get_post_meta($post_id, 'rooster_slots', true),
            'maybe' => (array) get_post_meta($post_id, 'rooster_slots_maybe', true),
        ];
    }

    private function format_week_label(string $week_key): string
    {
        if (!preg_match('/^(?<year>\d{4})-W(?<week>\d{2})$/', $week_key, $matches)) {
            return $week_key;
        }

        $date = new \DateTimeImmutable();
        $date = $date->setISODate((int) $matches['year'], (int) $matches['week']);
        $start = $date;
        $end = $date->modify('+6 days');

        return sprintf(
            'Week %s (%s t/m %s)',
            $matches['week'],
            $start->format('d-m-Y'),
            $end->format('d-m-Y')
        );
    }

    private function format_slots_label(array $slot_ids, string $week): string
    {
        if (!$slot_ids) {
            return '—';
        }

        $day_labels = $this->get_days();
        $slot_map = $this->get_slot_label_map($day_labels, $week);
        $labels = [];
        foreach ($slot_ids as $slot_id) {
            if (!is_string($slot_id)) {
                continue;
            }
            if (isset($slot_map[$slot_id])) {
                $labels[] = $slot_map[$slot_id];
                continue;
            }
            if (!str_contains($slot_id, '|')) {
                continue;
            }
            [$day_key, $time] = explode('|', $slot_id, 2);
            $day_label = $day_labels[$day_key] ?? $day_key;
            $labels[] = $day_label . ' ' . str_replace('-', '–', $time);
        }

        return $labels ? implode(', ', $labels) : '—';
    }

    private function get_slot_time_label_map(string $week): array
    {
        $slots_by_day = $this->get_slots_for_week($week);
        $map = [];
        foreach ($slots_by_day as $day_key => $slots) {
            foreach ($slots as $slot) {
                if (!isset($slot['start'], $slot['end'])) {
                    continue;
                }
                $slot_id = $this->build_slot_id($day_key, $slot['start'], $slot['end']);
                $time_label = $slot['start'] . '–' . $slot['end'];
                $name = isset($slot['name']) ? trim((string) $slot['name']) : '';
                $label = $name ? $name . ' (' . $time_label . ')' : $time_label;
                $map[$slot_id] = $label;
            }
        }
        return $map;
    }

    private function get_slot_label_map(array $day_labels, string $week): array
    {
        $slots_by_day = $this->get_slots_for_week($week);
        $map = [];
        foreach ($slots_by_day as $day_key => $slots) {
            $day_label = $day_labels[$day_key] ?? $day_key;
            foreach ($slots as $slot) {
                if (!isset($slot['start'], $slot['end'])) {
                    continue;
                }
                $slot_id = $this->build_slot_id($day_key, $slot['start'], $slot['end']);
                $time_label = $slot['start'] . '–' . $slot['end'];
                $name = isset($slot['name']) ? trim((string) $slot['name']) : '';
                $label = $name ? $day_label . ' ' . $name . ' (' . $time_label . ')' : $day_label . ' ' . $time_label;
                $map[$slot_id] = $label;
            }
        }
        return $map;
    }
}
