<?php
// phpBB Mentions maintained translation; GPL-2.0-only.
if (!defined('IN_PHPBB')) { exit; }
$lang = array_merge(isset($lang) ? $lang : [], [
	'MENTION_LENGTH' => 'Caracteres mínimos',
	'MENTION_LENGTH_EXPLAIN' => 'Entre 2 y 255 caracteres antes de buscar destinatarios.',
	'MENTION_COLOR' => 'Color de las menciones',
	'MENTION_COLOR_EXPLAIN' => 'Color hexadecimal con o sin #.',
	'MENTION_COLOR_INVALID' => 'El color (%s) no es válido.',
	'MENTION_STYLE' => 'Estilo de letra',
	'MENTION_STYLE_EXPLAIN' => 'Apariencia de las menciones en los mensajes.',
	'MENTION_STYLE_INVALID' => 'El estilo (%s) no es válido.',
	'MENTION_STYLE_NONE' => 'Normal',
	'MENTION_STYLE_ITALIC' => 'Cursiva',
	'MENTION_STYLE_BOLD' => 'Negrita',
	'MENTION_STYLE_ITALIC_BOLD' => 'Negrita y cursiva',
	'MENTION_MAX_RESULTS' => 'Resultados máximos',
	'MENTION_MAX_RESULTS_EXPLAIN' => 'Entre 1 y 50 resultados totales de usuarios y grupos.',
	'MENTION_LARGE_GROUPS' => 'Umbral de mención masiva',
	'MENTION_LARGE_GROUPS_EXPLAIN' => 'Entre 1 y 50 miembros activos. También se aplica a destinatarios agregados del mensaje. Máximo absoluto: 500 candidatos únicos y 500 etiquetas.',
	'MENTION_LINK' => 'Enlace al perfil',
	'MENTION_LINK_EXPLAIN' => 'Enlazar las menciones al perfil del usuario.',
]);

$lang = array_merge($lang, [
    'MENTION_BACKGROUND' => 'Color de fondo del tag',
    'MENTION_BACKGROUND_EXPLAIN' => 'Hexadecimal con o sin #. Dejar vacío para usar el color del estilo activo.',
    'MENTION_TEXT' => 'Color del texto del tag',
    'MENTION_EMAIL' => 'Canales de notificación de menciones',
    'MENTION_EMAIL_EXPLAIN' => 'Solo foro bloquea los emails de menciones. Permitir email respeta la elección de cada usuario en Panel de Control de Usuario → Preferencias de foros → Editar opciones de notificación → Menciones. No activa el email automáticamente ni modifica preferencias existentes.',
    'MENTION_BOARD_ONLY' => 'Solo notificación en el foro',
    'MENTION_ALLOW_EMAIL' => 'Permitir email según preferencia del usuario',
]);

$lang = array_merge($lang, [
    'MENTION_PREVIEW' => 'Vista previa',
    'MENTION_PREVIEW_EXPLAIN' => 'Se actualiza mientras editás, antes de guardar. Con fondo vacío el color final depende del estilo del foro; aquí se muestra un color de referencia.',
    'MENTION_PREVIEW_SAMPLE' => 'así se verá la mención en el mensaje.',
    'MENTION_PREVIEW_INVALID' => 'Introducí colores hexadecimales válidos para actualizar la muestra.',
]);
