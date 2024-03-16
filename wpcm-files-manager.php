<?php
/**
 * Plugin Name: WPCM Files Manager
 * Description: Um gerenciador de arquivos com segurança adicional para administradores do WordPress.
 * Version: 3.0
 * Author: Daniel Oliveira da Paixão
 */

// Adiciona o menu do gerenciador de arquivos no painel administrativo
function wpcm_fm_add_admin_menu() {
    add_menu_page('WPCM Files Manager', 'WPCM Files Manager', 'manage_options', 'wpcm-files-manager', 'wpcm_fm_files_manager_page');
    add_submenu_page('wpcm-files-manager', 'Configurações', 'Configurações', 'manage_options', 'wpcm-files-manager-settings', 'wpcm_fm_files_manager_settings_page');
}
add_action('admin_menu', 'wpcm_fm_add_admin_menu');

// Renderiza a página de configurações do gerenciador de arquivos
function wpcm_fm_files_manager_settings_page() {
    echo '<div class="wrap"><h1>Configurações do WPCM Files Manager</h1>';

    if (isset($_POST['submit_security_questions'])) {
        if ($_POST['security_answer_1'] === WPCM_FM_SECURITY_ANSWER_1 && $_POST['security_answer_2'] === WPCM_FM_SECURITY_ANSWER_2) {
            $allowed_ip = sanitize_text_field($_POST['allowed_ip']);
            if (filter_var($allowed_ip, FILTER_VALIDATE_IP)) {
                update_option('wpcm_fm_allowed_ip', $allowed_ip);
                echo '<div class="updated"><p>IP permitido salvo com sucesso.</p></div>';
            } else {
                echo '<div class="error"><p>O IP informado não é válido.</p></div>';
            }
        } else {
            echo '<p>Respostas incorretas. Tente novamente.</p>';
        }
    }

    echo '<form action="" method="post">';
    echo '<p>Para definir o IP permitido, responda às seguintes perguntas de segurança:</p>';
    echo '<p>' . WPCM_FM_SECURITY_QUESTION_1 . '</p>';
    echo '<input type="text" name="security_answer_1">';
    echo '<p>' . WPCM_FM_SECURITY_QUESTION_2 . '</p>';
    echo '<input type="text" name="security_answer_2">';
    echo '<p><label for="allowed_ip">IP permitido:</label></p>';
    echo '<input type="text" name="allowed_ip" id="allowed_ip">';
    echo '<input type="submit" value="Verificar" name="submit_security_questions">';
    echo '</form>';
    echo '</div>';
}

// Verifica se o usuário tem permissão para acessar o gerenciador de arquivos
function wpcm_fm_user_has_access() {
    $allowed_ip = get_option('wpcm_fm_allowed_ip', '');
    $user_ip = $_SERVER['REMOTE_ADDR'];
    return empty($allowed_ip) || $user_ip === $allowed_ip;
}

// Renderiza a página do gerenciador de arquivos
function wpcm_fm_files_manager_page() {
    echo '<style>
        .folder-icon {
            display: inline-block;
            background: url("https://midiarondonia.com.br/wp-content/uploads/2024/03/imagem1710612295.webp") no-repeat center center;
            background-size: contain;
            width: 50px;
            height: 50px;
        }
        .folder-name {
            display: inline-block;
            vertical-align: top;
            padding-left: 10px;
        }
    </style>';

    if (current_user_can('manage_options') && wpcm_fm_user_has_access()) {
        echo '<div class="wrap"><h1>WPCM Files Manager</h1><p>Caminho do site: ' . ABSPATH . '</p>';
        $files = scandir(ABSPATH);
        echo '<h2>Arquivos e diretórios:</h2><ul>';
        foreach ($files as $file) {
            if ($file != '.' && $file != '..') {
                if (is_dir(ABSPATH . $file)) {
                    echo '<li><div class="folder-icon"></div><div class="folder-name">' . htmlspecialchars($file) . '</div></li>';
                } else {
                    echo '<li>' . htmlspecialchars($file) . '</li>';
                }
            }
        }
        echo '</ul>';
        echo '<h2>Enviar um novo arquivo:</h2><form action="" method="post" enctype="multipart/form-data">';
        echo '<input type="file" name="new_file"><input type="submit" value="Upload" name="upload"></form>';
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
        echo '</div>';
    } else {
        echo '<div class="wrap"><h1>Acesso Negado</h1><p>Você não tem permissão para acessar esta página.</p></div>';
    }
}

// Ações ao ativar e desativar o plugin
function wpcm_fm_activate() {
    add_option('wpcm_fm_allowed_ip', '');
}
register_activation_hook(__FILE__, 'wpcm_fm_activate');

function wpcm_fm_deactivate() {
    delete_option('wpcm_fm_allowed_ip');
}
register_deactivation_hook(__FILE__, 'wpcm_fm_deactivate');
