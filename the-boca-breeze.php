<?php
/**
 * Plugin Name: The Boca Breeze
 * Description: Keep your WordPress content clean, clear, and escape-free. The Boca Breeze fixes escaped characters and keeps your site fresh — powered by Del Boca Dev 🌴.
 * Version: 1.0.4
 * Author: Shelby Gonzales
 * Author URI: https://delboca.dev/
 * Plugin URI: https://github.com/DelBocaDev/the-boca-breeze
 * License: GPL2
 */

defined('ABSPATH') || exit;

add_action('admin_menu', 'bocabreeze_add_admin_menu');
add_action('admin_init', 'bocabreeze_process_cleanup');
add_action('admin_enqueue_scripts', 'bocabreeze_enqueue_styles');

function bocabreeze_add_admin_menu() {
    add_submenu_page(
        'tools.php',
        'The Boca Breeze',
        'The Boca Breeze',
        'manage_options',
        'the-boca-breeze',
        'bocabreeze_render_settings_page'
    );
}

function bocabreeze_render_settings_page() {
    $selected_fields = [];
    $preview_html = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $selected_fields = isset($_POST['bocabreeze_fields']) ? (array) $_POST['bocabreeze_fields'] : [];

        if (isset($_POST['bocabreeze_preview'])) {
            global $wpdb;

            $posts = $wpdb->get_results("
                SELECT ID, post_title, post_content, post_excerpt
                FROM {$wpdb->posts}
                WHERE post_type = 'post'
            ");

            $affected = [];

            foreach ($posts as $post) {
                $changes = [];

                if (in_array('title', $selected_fields) && strpos($post->post_title, "\'") !== false) {
                    $changes[] = 'Title';
                }
                if (in_array('content', $selected_fields) && strpos($post->post_content, "\'") !== false) {
                    $changes[] = 'Content';
                }
                if (in_array('excerpt', $selected_fields) && strpos($post->post_excerpt, "\'") !== false) {
                    $changes[] = 'Excerpt';
                }

                if (!empty($changes)) {
                    $affected[] = [
                        'title' => $post->post_title,
                        'fields' => implode(', ', $changes),
                    ];
                }
            }

            if (!empty($affected)) {
                $preview_html = "<div class='notice notice-info'><p><strong>The Boca Breeze Preview:</strong> The following posts will be affected:</p><ul style='padding-left:20px;'>";
                foreach ($affected as $item) {
                    $preview_html .= "<li><strong>" . esc_html($item['title']) . "</strong> (Fields: " . esc_html($item['fields']) . ")</li>";
                }
                $preview_html .= "</ul></div>";
            } else {
                $preview_html = "<div class='notice notice-success'><p><strong>The Boca Breeze Preview:</strong> No posts need scrubbing. You're all clear!</p></div>";
            }
        }
    }
    ?>
    <div class="wrap">
        <h1>The Boca Breeze 🌴</h1>
        <p style="font-size: 16px; max-width: 600px;">
            The Boca Breeze is your go-to tool for cleaning up messy punctuation across your WordPress site.
            Remove escaped characters like <code>\'</code> from post content, titles, and excerpts — and preview the cleanup before it happens.
            Powered by <strong>Del Boca Dev</strong>, designed for breezy content management. 🧼
        </p>

        <?php echo $preview_html; ?>

        <form method="post" id="bocabreeze-form">
            <input type="hidden" name="bocabreeze_run" value="1">
            <p>Select which fields to scrub:</p>
            <p><a href="#" id="toggle-fields">Check All</a></p>
            <label><input type="checkbox" name="bocabreeze_fields[]" value="title" <?php checked(in_array('title', $selected_fields)); ?>> Post Title</label><br>
            <label><input type="checkbox" name="bocabreeze_fields[]" value="content" <?php checked(in_array('content', $selected_fields)); ?>> Post Content</label><br>
            <label><input type="checkbox" name="bocabreeze_fields[]" value="excerpt" <?php checked(in_array('excerpt', $selected_fields)); ?>> Post Excerpt</label><br><br>

            <div style="display: flex; gap: 10px; align-items: center; margin-top: 20px;">
                <?php submit_button('Preview The Breeze', 'secondary', 'preview_breeze', false); ?>
                <?php submit_button('Run The Breeze', 'primary', 'bocabreeze_submit', false); ?>
            </div>
        </form>

        <script>
        document.addEventListener('DOMContentLoaded', function () {
            const toggleLink = document.getElementById('toggle-fields');
            const checkboxes = document.querySelectorAll('input[name="bocabreeze_fields[]"]');
            const form = document.getElementById('bocabreeze-form');
            const previewBtn = document.querySelector('input[name="preview_breeze"]');

            const allChecked = Array.from(checkboxes).every(cb => cb.checked);
            toggleLink.textContent = allChecked ? 'Uncheck All' : 'Check All';

            toggleLink.addEventListener('click', function (e) {
                e.preventDefault();
                const allCheckedNow = Array.from(checkboxes).every(cb => cb.checked);

                checkboxes.forEach(cb => cb.checked = !allCheckedNow);
                toggleLink.textContent = allCheckedNow ? 'Check All' : 'Uncheck All';
            });

            form.addEventListener('submit', function(e) {
                const freshCheckboxes = document.querySelectorAll('input[name="bocabreeze_fields[]"]:checked');

                if (freshCheckboxes.length === 0) {
                    e.preventDefault();
                    alert("Please select at least one field to scrub 🌴");
                    return;
                }

                const existing = document.querySelector('input[name="bocabreeze_preview"]');
                if (existing) existing.remove();

                if (document.activeElement.name === "preview_breeze") {
                    const previewInput = document.createElement('input');
                    previewInput.type = 'hidden';
                    previewInput.name = 'bocabreeze_preview';
                    previewInput.value = '1';
                    form.appendChild(previewInput);
                } else if (document.activeElement.name === "bocabreeze_submit") {
                    const confirmed = confirm("Are you sure you want to scrub these fields? This cannot be undone! 🌴");
                    if (!confirmed) {
                        e.preventDefault();
                        return;
                    }
                }
            });
        });
        </script>
    </div>
    <?php
}

function bocabreeze_process_cleanup() {
    if (!current_user_can('manage_options') || !isset($_POST['bocabreeze_run']) || isset($_POST['bocabreeze_preview'])) {
        return;
    }

    $fields = isset($_POST['bocabreeze_fields']) ? (array) $_POST['bocabreeze_fields'] : [];

    if (empty($fields)) {
        return;
    }

    global $wpdb;

    $posts = $wpdb->get_results("
        SELECT ID, post_title, post_content, post_excerpt
        FROM {$wpdb->posts}
        WHERE post_type = 'post'
    ");

    $count = 0;

    foreach ($posts as $post) {
        $data = [];

        if (in_array('title', $fields)) {
            $new_title = str_replace(["\\'", "\'"], "'", $post->post_title);
            if ($new_title !== $post->post_title) {
                $data['post_title'] = $new_title;
            }
        }

        if (in_array('content', $fields)) {
            $new_content = str_replace(["\\'", "\'"], "'", $post->post_content);
            if ($new_content !== $post->post_content) {
                $data['post_content'] = $new_content;
            }
        }

        if (in_array('excerpt', $fields)) {
            $new_excerpt = str_replace(["\\'", "\'"], "'", $post->post_excerpt);
            if ($new_excerpt !== $post->post_excerpt) {
                $data['post_excerpt'] = $new_excerpt;
            }
        }

        if (!empty($data)) {
            $wpdb->update($wpdb->posts, $data, ['ID' => $post->ID]);
            $count++;
        }
    }

    if ($count > 0) {
        add_action('admin_notices', function () use ($count) {
            echo "<div class='notice notice-success'><p><strong>The Boca Breeze:</strong> Cleaned {$count} post(s). You’re fresh as Florida air. ☀️</p></div>";
        });
    } else {
        add_action('admin_notices', function () {
            echo "<div class='notice notice-warning'><p><strong>The Boca Breeze:</strong> No changes were made. Everything was already squeaky clean. 🧽</p></div>";
        });
    }
}

function bocabreeze_enqueue_styles($hook) {
    if ($hook !== 'tools_page_the-boca-breeze') return;

    wp_enqueue_style(
        'bocabreeze-style',
        plugin_dir_url(__FILE__) . 'admin-style.css',
        [],
        '1.0.4'
    );
}