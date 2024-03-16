<?php
/**
 * Plugin Name: WPCM Files Manager
 * Description: Um gerenciador de arquivos com segurança adicional para administradores do WordPress.
 * Version: 3.0
 * Author: Daniel Oliveira da Paixão
 */

// Adiciona o menu do gerenciador de arquivos no painel administrativo
function wpcm_fm_add_admin_menu() {
    add_menu_page('WPCM Files Manager', 'WPCM Files Manager', 'manage_files', 'wpcm-files-manager', 'wpcm_fm_files_manager_page');
    add_submenu_page('wpcm-files-manager', 'Configurações', 'Configurações', 'manage_files', 'wpcm-files-manager-settings', 'wpcm_fm_files_manager_settings_page');
}
add_action('admin_menu', 'wpcm_fm_add_admin_menu');

// Renderiza a página de configurações do gerenciador de arquivos
function wpcm_fm_files_manager_settings_page() {
    if (isset($_POST['save_ip']) && isset($_POST['allowed_ip']) && wp_verify_nonce($_POST['_wpnonce'], 'wpcm_fm_save_ip')) {
        $allowed_ip = sanitize_text_field($_POST['allowed_ip']);
        if (filter_var($allowed_ip, FILTER_VALIDATE_IP)) {
            update_option('wpcm_fm_allowed_ip', $allowed_ip);
            echo '<div class="updated"><p>IP permitido salvo com sucesso.</p></div>';
        } else {
            echo '<div class="error"><p>O IP informado não é válido.</p></div>';
        }
    }
    $allowed_ip = get_option('wpcm_fm_allowed_ip', '');
    echo '<div class="wrap"><h1>Configurações do WPCM Files Manager</h1><form method="post" action="">';
    wp_nonce_field('wpcm_fm_save_ip');
    echo '<table class="form-table"><tr><th scope="row"><label for="allowed_ip">IP permitido</label></th><td><input name="allowed_ip" type="text" id="allowed_ip" value="' . esc_attr($allowed_ip) . '" class="regular-text"></td></tr></table>';
    echo '<p class="submit"><input type="submit" name="save_ip" class="button-primary" value="Salvar IP"></p></form></div>';
}

// Verifica se o usuário tem permissão para acessar o gerenciador de arquivos
function wpcm_fm_user_has_access() {
    $allowed_ip = get_option('wpcm_fm_allowed_ip', '');
    $user_ip = $_SERVER['REMOTE_ADDR'];
    return empty($allowed_ip) || $user_ip === $allowed_ip;
}

// Renderiza a página do gerenciador de arquivos
function wpcm_fm_files_manager_page() {
    if (current_user_can('manage_files') && isset($_REQUEST['security_key']) && isset($_REQUEST['_wpnonce']) && wpcm_fm_user_has_access()) {
        $security_key = base64_decode(urldecode($_REQUEST['security_key']));
        $security_key = openssl_decrypt($security_key, 'AES-256-CBC', WPCM_FM_ENCRYPTION_KEY);
        if ($security_key === WPCM_FM_HASH && wp_verify_nonce($_REQUEST['_wpnonce'], 'wpcm-fm-nonce')) {
            echo '<h1>WPCM Files Manager</h1><p>Caminho do site: ' . ABSPATH . '</p>';
            // Lógica para listar arquivos
            $files = scandir(ABSPATH);
            echo '<h2>Arquivos e diretórios:</h2><ul>';
            foreach ($files as $file) {
                if ($file != '.' && $file != '..') {
                    echo '<li>' . htmlspecialchars($file) . '</li>';
                }
            }
            echo '</ul>';
            // Formulário para enviar um novo arquivo
            echo '<h2>Enviar um novo arquivo:</h2><form action="" method="post" enctype="multipart/form-data"><input type="file" name="new_file"><input type="submit" value="Upload" name="upload"></form>';
            if (isset($_POST['upload'])) {
                $target_file = ABSPATH . basename($_FILES["new_file"]["name"]);
                if (move_uploaded_file($_FILES["new_file"]["tmp_name"], $target_file)) {
                    echo '<p>Arquivo enviado com sucesso.</p>';
                    if (pathinfo($target_file, PATHINFO_EXTENSION) === 'zip') {
                        $zip = new ZipArchive;
                            if ($zip->open($target_file) === TRUE) {
                            $zip->extractTo(ABSPATH);
                            $zip->close();
                            echo "<p>Arquivo ZIP descompactado com sucesso.</p>";
                        } else {
                            echo "<p>Erro ao descompactar o arquivo ZIP.</p>";
                        }
                    }
                } else {
                    echo '<p>Erro ao enviar o arquivo.</p>';
                }
            }
            // Adicione aqui a lógica para editar e excluir arquivos conforme necessário
        } else {
            echo '<h1>Acesso Negado</h1>';
            echo '<p>As credenciais de segurança são inválidas.</p>';
        }
    } else {
        echo '<h1>Acesso Negado</h1>';
        echo '<p>Você não tem permissão para acessar esta página.</p>';
    }
}

// Adiciona a chave de segurança e o nonce ao URL do menu
function wpcm_fm_add_security_key($parent_file) {
    global $submenu;
    if (isset($submenu['wpcm-files-manager'])) {
        foreach ($submenu['wpcm-files-manager'] as &$item) {
            $item[2] .= '&security_key=' . urlencode(base64_encode(openssl_encrypt(WPCM_FM_HASH, 'AES-256-CBC', WPCM_FM_ENCRYPTION_KEY)));
            $item[2] .= '&_wpnonce=' . wp_create_nonce('wpcm-fm-nonce');
        }
    }
    return $parent_file;
}
add_filter('parent_file', 'wpcm_fm_add_security_key');

// Ações ao ativar e desativar o plugin
function wpcm_fm_activate() {
    update_option('wpcm_fm_plugin_activated', true);
}
register_activation_hook(__FILE__, 'wpcm_fm_activate');

function wpcm_fm_deactivate() {
    delete_option('wpcm_fm_allowed_ip');
    update_option('wpcm_fm_plugin_activated', false);
}
register_deactivation_hook(__FILE__, 'wpcm_fm_deactivate');

