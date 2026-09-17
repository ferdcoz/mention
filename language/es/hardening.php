<?php
// phpBB Mentions maintained translation; GPL-2.0-only.
if (!defined('IN_PHPBB')) { exit; }
$lang = array_merge(isset($lang) ? $lang : [], [
	'MENTION_LIMIT_EXCEEDED' => 'El mensaje supera el límite de seguridad de %s candidatos únicos o etiquetas de mención. Reduce las menciones.',
	'MENTION_MASS_DENIED' => 'Para esta cantidad de destinatarios se necesita el permiso para mencionar grupos grandes.',
	'MENTION_CONFIRM_REQUIRED' => 'Confirma que quieres notificar hasta %s destinatarios y vuelve a enviar. Si cambias el mensaje deberás confirmar otra vez.',
	'MENTION_CONFIRM_GROUP' => 'Este grupo tiene {CNT} miembros activos. ¿Insertar la mención? También deberás confirmar antes de enviar.',
	'LOG_MENTION_MASS' => 'Mención masiva enviada: mensaje %1$s, %2$s destinatarios habilitados.',
]);
