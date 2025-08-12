<?php
/*
Plugin Name: WhatsApp Multilang Button
Plugin URI:  https://example.com
Description: Botón flotante de WhatsApp que cambia de número según el idioma (WPML). Panel admin para gestionar números por idioma.
Version:     1.0
Author:      Tu Nombre
Text Domain: wmb
*/

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Opciones por defecto
 */
function wmb_get_default_options() {
    return array(
        'pairs' => array( // ejemplo por defecto
            // 'es' => '+34666666666',
            // 'es-ar' => '+549116666666',
        ),
        'auto_inject' => 1, // inyectar en wp_footer por defecto
    );
}

/**
 * Obtiene las opciones actuales (merge con defaults)
 */
function wmb_get_options() {
    $defaults = wmb_get_default_options();
    $opts = get_option('wmb_options', array());
    return wp_parse_args($opts, $defaults);
}

/**
 * Shortcode que devuelve HTML del botón
 */
function wmb_whatsapp_shortcode() {
    // Obtener idioma con WPML si existe, o usar la función de WP (get_locale) como fallback
    $current_lang = null;
    if ( defined('ICL_SITEPRESS_VERSION') ) {
        $current_lang = apply_filters('wpml_current_language', NULL);
    } else {
        // fallback: tomar locale y simplificar a código idioma (es, en, pt-br, etc)
        $locale = get_locale(); // ej 'es_ES'
        $current_lang = strtolower(str_replace('_', '-', $locale)); // 'es-es'
        // algunos sites solo usan 'es'
        $current_lang = substr($current_lang, 0, 5); // limitar longitud segura
    }

    $opts = wmb_get_options();
    $pairs = isset($opts['pairs']) && is_array($opts['pairs']) ? $opts['pairs'] : array();

    // Buscar coincidencia exacta o por prefijo (ej: 'es' si existe)
    $telefono = '';
    if ( isset($pairs[$current_lang]) ) {
        $telefono = $pairs[$current_lang];
    } else {
        // intentar solo el prefijo de idioma (antes del guion)
        $prefix = explode('-', $current_lang)[0];
        if ( isset($pairs[$prefix]) ) {
            $telefono = $pairs[$prefix];
        }
    }

    // Si no hay número, no mostrar nada
    if ( empty($telefono) ) {
        return ''; // o un fallback si quieres
    }

    $telefono_url = preg_replace('/\D/', '', $telefono);
    $wa_url = 'https://wa.me/' . $telefono_url;

    // HTML del botón (SVG inline)
    $html  = '<a class="wmb-btn-whatsapp" href="'. esc_url($wa_url) .'" target="_blank" rel="noopener noreferrer" aria-label="Chatear en WhatsApp">';
    $html .= '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="white" viewBox="0 0 24 24" aria-hidden="true"><path d="M20.52 3.48A11.93 11.93 0 0012 0C5.373 0 0 5.373 0 12c0 2.11.554 4.09 1.607 5.823L0 24l6.41-1.64A11.91 11.91 0 0012 24c6.627 0 12-5.373 12-12 0-3.207-1.24-6.22-3.48-8.52zM17.44 16.61c-.29-.15-1.77-.88-2.04-.98-.27-.1-.48-.15-.68.15-.2.3-.77.98-.95 1.17-.17.19-.34.22-.65.07-.3-.15-1.26-.47-2.4-1.49-.88-.79-1.48-1.75-1.65-2.05-.17-.3-.02-.46.13-.61.13-.13.3-.35.45-.53.15-.18.2-.3.3-.5.1-.2.05-.38-.03-.52-.07-.15-.68-1.63-.92-2.22-.24-.59-.48-.51-.67-.52-.17-.01-.37-.01-.57-.01-.2 0-.52.07-.8.37-.27.3-1.04 1.02-1.04 2.48 0 1.47 1.07 2.88 1.21 3.08.15.2 2.1 3.21 5.08 4.49.7.3 1.26.49 1.69.63.71.23 1.36.2 1.87.12.57-.09 1.77-.72 2.02-1.42.25-.7.25-1.29.17-1.41-.07-.13-.27-.2-.57-.35z"/></svg>';
    $html .= '</a>';

    return $html;
}
add_shortcode('wmb_whatsapp', 'wmb_whatsapp_shortcode');


/**
 * Inyecta CSS/JS frontend
 */
function wmb_frontend_assets() {
    // Estilos en línea (fácil y único archivo)
    $css = "
    .wmb-btn-whatsapp{position:fixed;bottom:80px;left:20px;background-color:#25D366;width:56px;height:56px;border-radius:50%;display:flex;justify-content:center;align-items:center;box-shadow:0 4px 10px rgba(0,0,0,0.3);z-index:99999;transition:background-color .2s ease;padding:0;text-decoration:none}
    .wmb-btn-whatsapp:hover{background-color:#1ebe57}
    .wmb-btn-whatsapp svg{display:block;width:24px;height:24px}
    @media(max-width:768px){ .wmb-btn-whatsapp{bottom:24px;left:16px;width:48px;height:48px} .wmb-btn-whatsapp svg{width:20px;height:20px} }
    ";
    wp_add_inline_style('wp-block-library', $css);
}
add_action('wp_enqueue_scripts', 'wmb_frontend_assets');


/**
 * Opción para inyectar automáticamente en wp_footer si el admin la activa
 */
function wmb_maybe_inject_footer() {
    $opts = wmb_get_options();
    if ( ! empty($opts['auto_inject']) ) {
        echo do_shortcode('[wmb_whatsapp]');
    }
}
add_action('wp_footer', 'wmb_maybe_inject_footer', 100);


/**
 * Admin: menú y página de ajustes
 */
function wmb_admin_menu() {
    add_options_page('WhatsApp Multilang', 'WhatsApp Multilang', 'manage_options', 'wmb-settings', 'wmb_options_page');
}
add_action('admin_menu', 'wmb_admin_menu');

function wmb_options_page() {
    if ( ! current_user_can('manage_options') ) return;
    $opts = wmb_get_options();
    $pairs = isset($opts['pairs']) ? $opts['pairs'] : array();
    $auto_inject = isset($opts['auto_inject']) ? $opts['auto_inject'] : 0;

    // Guardado simple (POST)
    if ( isset($_POST['wmb_save']) && check_admin_referer('wmb_save_action', 'wmb_save_nonce') ) {
        $langs = isset($_POST['wmb_lang']) ? array_map('sanitize_text_field', (array) $_POST['wmb_lang']) : array();
        $nums  = isset($_POST['wmb_num']) ? array_map('sanitize_text_field', (array) $_POST['wmb_num']) : array();

        $new_pairs = array();
        for ($i=0; $i < count($langs); $i++) {
            $l = trim($langs[$i]);
            $n = trim($nums[$i]);
            if ( $l !== '' && $n !== '' ) {
                $new_pairs[$l] = $n;
            }
        }
        $auto_inject = isset($_POST['wmb_auto_inject']) ? 1 : 0;

        $save = array('pairs' => $new_pairs, 'auto_inject' => $auto_inject);
        update_option('wmb_options', $save);
        $pairs = $new_pairs;
        $auto_inject = $auto_inject;
        echo '<div class="updated"><p>Guardado correctamente.</p></div>';
    }

    ?>
    <div class="wrap">
        <h1>WhatsApp Multilang</h1>
        <p>Agrega números por código de idioma (ej: <code>es</code>, <code>es-ar</code>, <code>pt-br</code>, <code>en</code>).</p>

        <form method="post" action="">
            <?php wp_nonce_field('wmb_save_action','wmb_save_nonce'); ?>

            <table class="widefat fixed" id="wmb-table">
                <thead><tr><th width="200">Idioma (código)</th><th>Número (formato +Numero de WhatsApp)</th><th width="80">Acción</th></tr></thead>
                <tbody>
                    <?php
                    if ( ! empty($pairs) ) {
                        foreach ($pairs as $lang => $num) {
                            echo '<tr>';
                            echo '<td><input type="text" class="regular-text wmb-lang" name="wmb_lang[]" value="'.esc_attr($lang).'"></td>';
                            echo '<td><input type="text" class="regular-text wmb-num" name="wmb_num[]" value="'.esc_attr($num).'"></td>';
                            echo '<td><button class="button wmb-remove" type="button">Eliminar</button></td>';
                            echo '</tr>';
                        }
                    } else {
                        // fila vacía inicial
                        echo '<tr><td><input type="text" class="regular-text wmb-lang" name="wmb_lang[]" value=""></td><td><input type="text" class="regular-text wmb-num" name="wmb_num[]" value=""></td><td><button class="button wmb-remove" type="button">Eliminar</button></td></tr>';
                    }
                    ?>
                </tbody>
            </table>

            <p>
                <button id="wmb-add-row" class="button button-primary" type="button">Agregar fila</button>
            </p>

            <p>
                <label><input type="checkbox" name="wmb_auto_inject" <?php checked($auto_inject,1); ?>> Inyectar el botón automáticamente en todo el sitio (wp_footer)</label>
            </p>

            <p>
                <input type="submit" class="button button-primary" name="wmb_save" value="Guardar cambios">
            </p>
        </form>

        <h2>Uso</h2>
        <p>Shortcode para insertar manualmente donde quieras: <code>[wmb_whatsapp]</code></p>
        <p>Si activas "Inyectar automáticamente", aparecerá en todo el sitio sin necesidad de insertar el shortcode.</p>
    </div>

    <script>
    (function(){
        // JS simple para agregar/eliminar filas (admin)
        document.addEventListener('click', function(e){
            if ( e.target && e.target.id === 'wmb-add-row' ) {
                e.preventDefault();
                var tbody = document.querySelector('#wmb-table tbody');
                var tr = document.createElement('tr');
                tr.innerHTML = '<td><input type="text" class="regular-text wmb-lang" name="wmb_lang[]" value=""></td><td><input type="text" class="regular-text wmb-num" name="wmb_num[]" value=""></td><td><button class="button wmb-remove" type="button">Eliminar</button></td>';
                tbody.appendChild(tr);
            }
            if ( e.target && e.target.classList && e.target.classList.contains('wmb-remove') ) {
                e.preventDefault();
                var row = e.target.closest('tr');
                if ( row ) row.parentNode.removeChild(row);
            }
        }, false);
    })();
    </script>

    <style>
    #wmb-table .wmb-lang{width:100px}
    #wmb-table .wmb-num{width:300px}
    </style>
    <?php
}

/* FIN PLUGIN */