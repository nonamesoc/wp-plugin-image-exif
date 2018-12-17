<?php
/*
Plugin Name: EXIF
Plugin URI: http://страница_автора_плагина
Description: изменяет EXIF изображений
Version: Номер версии плагина, например: 1.0
Author: noname
Author URI: http://страница_автора_плагина
*/

require "vendor/autoload.php";
use lsolesen\pel\PelDataWindow;
use lsolesen\pel\PelJpeg;
use lsolesen\pel\PelTiff;
use lsolesen\pel\PelExif;
use lsolesen\pel\PelIfd;
use lsolesen\pel\PelTag;
use lsolesen\pel\PelEntryAscii;
//use lsolesen\pel\PelDataWindowOffsetException

//вставка заголовков exif при обновлнеии вложения
add_action( 'attachment_updated', 'update_exif_attach', 10, 3 );
function update_exif_attach($post_ID, $post_after, $post_before){
	if($post_after->post_mime_type == 'image/jpeg'){
		if($post_after->post_parent != 0){
			$img = $post_after;
			$post_parent =get_post($img->post_parent);
			$jpeg = create_PelJpeg($img->guid);
			$ifd0 = $jpeg->getExif()->getTiff()->getIfd();
			$entry1 = $ifd0->getEntry(PelTag::DOCUMENT_NAME);
			$entry2 = $ifd0->getEntry(PelTag::IMAGE_DESCRIPTION);
			if( $entry1 == null) {
				$entry1 = new PelEntryAscii(PelTag::DOCUMENT_NAME, $post_parent->post_title);
				$ifd0->addEntry( $entry1 );
			}
			else {
				$entry1->setValue( $post_parent->post_title );	
			}
			$path = stristr( $img->guid, 'wp-content' );
			$jpeg->saveFile('../'.$path);
			$content_post = get_post($post_parent->ID);
			$content = $content_post->post_content;
			$content = strip_shortcodes($content);
			$content = apply_filters('the_content', $content);
			$content = str_replace(']]>', ']]&gt;', $content);
			$content = strip_tags($content);
			if($entry2 == null) {
				$entry2 = new PelEntryAscii(PelTag::IMAGE_DESCRIPTION, $content);
				$ifd0->addEntry( $entry2 );
			}
			else {
				$entry2->setValue( $content );	
			}
			$path = stristr( $img->guid, 'wp-content' );
			$jpeg->saveFile('../'.$path);
		}
	}
}
//вставка заголовков exif при добавлении файла через страницу редактирования
add_action( 'add_attachment', 'create_exif_attach' );
function create_exif_attach( $post_ID ){
	$img = get_post($post_ID);
	if($img->post_mime_type == 'image/jpeg'){
		if($img->post_parent != 0){
			$post_parent =get_post($img->post_parent);
			$jpeg = create_PelJpeg($img->guid);
			$ifd0 = $jpeg->getExif()->getTiff()->getIfd();
			$entry1 = $ifd0->getEntry(PelTag::DOCUMENT_NAME);
			$entry2 = $ifd0->getEntry(PelTag::IMAGE_DESCRIPTION);
			if( $entry1 == null) {
				$entry1 = new PelEntryAscii(PelTag::DOCUMENT_NAME, $post_parent->post_title);
				$ifd0->addEntry( $entry1 );
			}
			else {
				$entry1->setValue( $post_parent->post_title );	
			}
			$path = stristr( $img->guid, 'wp-content' );
			$jpeg->saveFile('../'.$path);
			$content_post = get_post($post_parent->ID);
			$content = $content_post->post_content;
			$content = strip_shortcodes($content);
			$content = apply_filters('the_content', $content);
			$content = str_replace(']]>', ']]&gt;', $content);
			$content = strip_tags($content);
			if($entry2 == null) {
				$entry2 = new PelEntryAscii(PelTag::IMAGE_DESCRIPTION, $content);
				$ifd0->addEntry( $entry2 );
			}
			else {
				$entry2->setValue( $content );	
			}
			$path = stristr( $img->guid, 'wp-content' );
			$jpeg->saveFile('../'.$path);
		}
	}
}

//создание PelJpeg, у изображений без exif
function create_PelJpeg($jpeg){
		$jpeg = new PelJpeg($jpeg);
		$check = $jpeg->getExif();
		if( $check == null){
			$exif = new PelExif();
			$tiff = new PelTiff();
			$ifd = new PelIfd(PelIfd::IFD0);//PelIfd::IFD0
			$tiff->setIfd($ifd);
			$exif->setTiff($tiff);
			$jpeg->setExif($exif);
		}
		return $jpeg;
}

//получение entry по тегу
function get_tag_entry($jpeg, $tag){
		$jpeg = create_PelJpeg($jpeg);
		$ifd0 = $jpeg->getExif()->getTiff()->getIfd();
		$DOCUMENT_NAME = $ifd0->getEntry(PelTag::DOCUMENT_NAME);
		$IMAGE_DESCRIPTION = $ifd0->getEntry(PelTag::IMAGE_DESCRIPTION);
		if( $tag == 'DOCUMENT_NAME'){
			return $DOCUMENT_NAME;
		}
		if( $tag == 'IMAGE_DESCRIPTION'){
			return $IMAGE_DESCRIPTION;
		}
}
//получение поля по entry
function get_exif_entry($jpeg, $type){
	$entry1 = get_tag_entry($jpeg, 'DOCUMENT_NAME');
	$entry2 = get_tag_entry($jpeg, 'IMAGE_DESCRIPTION');
	if($entry1 == null) {
		$title = '';
	}
	else {
		$title = $entry1->getValue();
	}
	if($entry2 == null) {
		$desc = '';
	}
	else {
		$desc = $entry2->getValue();
	}
	if( $type == 'title' ){	
		return $title;
	}
	if( $type == 'desc' ){
		return $desc;
	}
}

// Добавляем блоки в основную колонку на страницах постов и пост. страниц
add_action('add_meta_boxes', 'myplugin_add_custom_box');
function myplugin_add_custom_box(){
	$screens = array( 'post', 'page' );
	add_meta_box( 'myplugin_sectionid', 'EXIF fields', 'myplugin_meta_box_callback', $screens, 'side' );
}

// HTML код блока
function myplugin_meta_box_callback( $post, $meta ){
	$screens = $meta['args'];

	wp_nonce_field( plugin_basename(__FILE__), 'myplugin_noncename' );

	$checked = (get_post_meta($post->ID, 'post_checkbox_exif',true) == 'on') ? ' checked="checked"' : '';
	echo '<input type="checkbox" id= "checkbox_exif" name="checkbox_exif"' . $checked . '/> Get value from the post
	<br/>';

//отображение полей exif
	$media = get_attached_media( 'image/jpeg', $post->ID );
	foreach ($media as $val) {
	    $media = $val;
		$attachment_id = $media->ID;
		$image_attributes = wp_get_attachment_image_src( $attachment_id );
		$title = get_exif_entry($media->guid, 'title');
		$desc = get_exif_entry($media->guid, 'desc');
		echo '<img src="' . $image_attributes[0] . '" width="' . $image_attributes[1] . '" height="' . $image_attributes[2] . '"><br/>';
		echo '<label for="myplugin_exif_title_' . $attachment_id . '">' . __("Title", 'myplugin_textdomain' ) . '</label> ';
		echo '<input type="text" id= "myplugin_exif_title_' . $attachment_id . '" name="myplugin_exif_title_' . $attachment_id . '" value="' . $title . '" size="25" />';
		echo '<label for="myplugin_exif_desc_' . $attachment_id . '">' . __("Description", 'myplugin_textdomain' ) . '</label> ';
		echo '<textarea id= "myplugin_exif_desc_' . $attachment_id . '" name="myplugin_exif_desc_' . $attachment_id . '">' . $desc . '</textarea>';
	}
}

// Сохраняем данные, когда пост сохраняется
add_action( 'save_post', 'exif_save' );
function exif_save( $post_id ) {
	$my_data = sanitize_text_field( $_POST['checkbox_exif'] );

	update_post_meta( $post_id, 'post_checkbox_exif', $my_data );

	if ( ! wp_verify_nonce( $_POST['myplugin_noncename'], plugin_basename(__FILE__) ) )
		return;

	if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) 
		return;

	if( ! current_user_can( 'edit_post', $post_id ) )
		return;

	$media = get_attached_media( 'image/jpeg', $post_id );
	foreach ($media as $val) {
		$media = $val;
		$attachment_id = $media->ID;
		$jpeg = create_PelJpeg($media->guid);
		$ifd0 = $jpeg->getExif()->getTiff()->getIfd();
		$entry1 = $ifd0->getEntry(PelTag::DOCUMENT_NAME);
		$entry2 = $ifd0->getEntry(PelTag::IMAGE_DESCRIPTION);
		if ( $_POST['checkbox_exif'] != 'on' ){
			if( $entry1 == null) {
				$entry1 = new PelEntryAscii(PelTag::DOCUMENT_NAME, $_POST['myplugin_exif_title_' . $attachment_id]);
				$ifd0->addEntry( $entry1 );
			}
			else {
				$entry1->setValue( $_POST['myplugin_exif_title_' . $attachment_id] );	
			}
			$path = stristr( $media->guid, 'wp-content' );
			$jpeg->saveFile('../'.$path);
			if($entry2 == null) {
				$entry2 = new PelEntryAscii(PelTag::IMAGE_DESCRIPTION, $_POST['myplugin_exif_desc_' . $attachment_id]);
				$ifd0->addEntry( $entry2 );
			}
			else {
				$entry2->setValue( $_POST['myplugin_exif_desc_' . $attachment_id] );	
			}
			$path = stristr( $media->guid, 'wp-content' );
			$jpeg->saveFile('../'.$path);
		}

		else{
			$post_parent =get_post($post_id);
			if( $entry1 == null) {
				$entry1 = new PelEntryAscii(PelTag::DOCUMENT_NAME, $post_parent->post_title);
				$ifd0->addEntry( $entry1 );
			}
			else {
				$entry1->setValue( $post_parent->post_title );	
			}
			$path = stristr( $media->guid, 'wp-content' );
			$jpeg->saveFile('../'.$path);
			$content = $post_parent->post_content;
			$content = strip_shortcodes($content);
			$content = apply_filters('the_content', $content);
			$content = str_replace(']]>', ']]&gt;', $content);
			$content = strip_tags($content);
			if($entry2 == null) {
				$entry2 = new PelEntryAscii(PelTag::IMAGE_DESCRIPTION, $content);
				$ifd0->addEntry( $entry2 );
			}
			else {
				$entry2->setValue( $content );	
			}
			$path = stristr( $media->guid, 'wp-content' );
			$jpeg->saveFile('../'.$path);
		}
	}
}

//ДОБАВЛЕНИЕ ПОЛЕЙ В РЕДАКТОР ИЗОБРАЖЕНИЯ
add_filter( 'attachment_fields_to_edit', 'exif_filds', null, 2 );
function exif_filds( $form_fields, $post ){
	if($post->post_mime_type == 'image/jpeg'){
		$title = get_exif_entry($post->guid, 'title');
		$desc = get_exif_entry($post->guid, 'desc');	
		$form_fields['exif_title'] = array(
			'label' => 'Title',
			'input' => '',
			'value' => $title
		);
		$form_fields['exif_desc'] = array(
			'label' => 'Description',
			'input' => 'textarea',
			'value' => $desc
		);
	}
	return $form_fields;
}
//сохранение exif на странице редактирования изображения
function exif_filds_save( $post, $attachment ) {
	if( isset( $attachment['exif_title'] ) )
		$jpeg = create_PelJpeg($post['attachment_url']);
		$ifd0 = $jpeg->getExif()->getTiff()->getIfd();
		$entry1 = $ifd0->getEntry(PelTag::DOCUMENT_NAME);
		$entry2 = $ifd0->getEntry(PelTag::IMAGE_DESCRIPTION);
		if( $entry1 == null) {
			$entry1 = new PelEntryAscii(PelTag::DOCUMENT_NAME, $attachment['exif_title']);
			$ifd0->addEntry( $entry1 );
		}
		else {
			$entry1->setValue( $attachment['exif_title'] );	
		}
		$path = stristr( $post['attachment_url'], 'wp-content' );
		$jpeg->saveFile('../'.$path);
		//добавить удаление
	if( isset( $attachment['exif_desc'] ) )
		if($entry2 == null) {
			$entry2 = new PelEntryAscii(PelTag::IMAGE_DESCRIPTION, $attachment['exif_desc']);
			$ifd0->addEntry( $entry2 );
		}
		else {
			$entry2->setValue( $attachment['exif_desc'] );	
		}
		$path = stristr( $post['attachment_url'], 'wp-content' );
		$jpeg->saveFile('../'.$path);
}
add_filter( 'attachment_fields_to_save', 'exif_filds_save', 10, 2 );

// обновление exif у всех изображений jpeg
function update_image_exif(){
	$args = array(
		'numberposts' => -1,
		'category'    => 0,
		'orderby'     => 'date',
		'order'       => 'DESC',
		'include'     => array(),
		'exclude'     => array(),
		'meta_key'    => '',
		'meta_value'  =>'',
		'post_type'   => array('post','page'),
		'suppress_filters' => true,
	);

	$posts = get_posts( $args );
	foreach($posts as $post){ setup_postdata($post);
	    $media = get_attached_media( 'image/jpeg', $post->ID );
	    foreach ($media as $val) {
		    $media = $val;
			$attachment_id = $media->ID;
			$image_attributes = wp_get_attachment_image_src( $attachment_id );
			$jpeg = create_PelJpeg($media->guid);
			$ifd0 = $jpeg->getExif()->getTiff()->getIfd();
			$entry1 = $ifd0->getEntry(PelTag::DOCUMENT_NAME);
			$entry2 = $ifd0->getEntry(PelTag::IMAGE_DESCRIPTION);
			if( $entry1 == null) {
				$entry1 = new PelEntryAscii(PelTag::DOCUMENT_NAME, $post->post_title);
				$ifd0->addEntry( $entry1 );
			}
			else {
				$entry1->setValue( $post->post_title );	
			}
			$path = stristr( $media->guid, 'wp-content' );
			$jpeg->saveFile('../'.$path);
			$content_post = get_post($post->ID);
			$content = $content_post->post_content;
			$content = strip_shortcodes($content);
			$content = apply_filters('the_content', $content);
			$content = str_replace(']]>', ']]&gt;', $content);
			$content = strip_tags($content);
			if($entry2 == null) {
				$entry2 = new PelEntryAscii(PelTag::IMAGE_DESCRIPTION, $content);
				$ifd0->addEntry( $entry2 );
			}
			else {
				$entry2->setValue( $content );	
			}
			$path = stristr( $media->guid, 'wp-content' );
			$jpeg->saveFile('../'.$path);
		}
	}

	wp_reset_postdata();
}

add_action('admin_menu', 'update_exif_page');
function update_exif_page(){
	add_options_page( 'Update_EXIF', 'Update_EXIF', 'manage_options', 'update_exif_slug', 'exif_options_page_output' );
}

// вставка exif из всех постов, при нажатии кнопки
if (isset($_POST['update_image_exif'])){
	update_image_exif();
}

function exif_options_page_output(){
	?>
	<div class="wrap">
		<h2><?php echo get_admin_page_title() ?></h2>
		<form action="/wp-admin/options-general.php?page=update_exif_slug" method="POST">
			<input name="update_image_exif" type="submit" value="Выполнить" />
		</form>
	</div>
	<?php
}


// Регистрируем настройки.
add_action('admin_init', 'plugin_settings');
function plugin_settings(){
	register_setting( 'exif_option_group', 'option_name', 'sanitize_callback' );
	add_settings_section( 'section_id', 'Update exif', '', 'update_exif_page' );  
}

?>