<?php
/*
Plugin Name: Askbox
Plugin URI: https://github.com/alisinfinite/askbox
Description: Like a Tumblr askbox, but for WordPress. Shortcode is `[askbox]`. That's it.
Version: 0.1
Author: Alis
Author URI: https://alis.me/
*/

// die if called directly
if(!function_exists('add_action')){
	echo 'No sweetie...';
	exit;
}

// shortcode
//attributes: (require) email and (require) name and (require) url
function askbox_shortcode($atts){
	$error = 0; $msg = false;
	
	$atts = shortcode_atts(
		array(
			'name' => true,
			'email' => true,
			'url' => true,
			'captcha' => array(
				array(
					'q' => 'Lain\'s name is?',
					'a' => 'Lain'
				)
			)
		),
		$atts, 'askbox'
	);
	
	// probably a better way to do this but meeeeeh...
	if($_POST['ask'] && is_array($_POST['ask'])) {
		// check our nonce and hardfail if it fails
		if(wp_verify_nonce($_REQUEST['ask_nonce'], 'askbox')){
			$ask = array(
				'name' => sanitize_text_field($_POST['ask']['name']),
				'email' => sanitize_email($_POST['ask']['email']),
				'url'  => sanitize_text_field($_POST['ask']['url']),
				'question' => sanitize_textarea_field($_POST['ask']['question']),
			);
		} else { $error = 1; }
		
		// check captcha
		if(askbox_captcha(sanitize_text_field($_POST['captcha']), sanitize_text_field($_POST['ask']['captcha']), $atts['captcha'])){
			if(submit_ask($ask)) { $error = 2; }
			else { $msg = 'Something went wrong! Please try again later...'; }
		} else { $msg = 'Are you human?'; }
	}
	
	// build ye html
	$r = '';
	
	if($msg){
		$r = '<p class="askmsg">'. $msg .'</p>';
	}
	
	$r .= '<form class="askbox" method="POST" action="'. htmlspecialchars($_SERVER["REQUEST_URI"]) .'"><input type="hidden" name="captcha" value="0">'. wp_nonce_field('askbox', 'ask_nonce', true, false);
	
	if($atts['name']){
		$ask['name'] = $ask['name'] ? $ask['name'] : 'Anonymous';
		$r .= '<p><label for="askn">Name</label> <input id="askn" name="ask[name]" type="text" value="'. $ask['name'] .'"></p>';
	}
	
	if($atts['email']){
		$r .= '<p><label for="aske">Email</label> <input id="aske" name="ask[email]" type="email" value="'. $ask['email'] .'"></p>';
	}
	
	if($atts['email']){
		$r .= '<p><label for="asku">URL</label> <input id="asku" name="ask[url]" type="url" value="'. $ask['url'] .'"></p>';
	}
	
	$r .= '<p><label for="askq">Ask me anything!</label> <textarea rows="7" id="askq" name="ask[question]">'. $ask['question'] .'</textarea></p>'.
		  '<p><label for="askc">'. $atts['captcha'][0]['q'] .'</lable> <input type="text" id="askc" name="ask[captcha]"></p>'.
		  '<p><input type="submit" value="Ask!" class="submit"></p>'.
		  '</form>';
	
	switch($error){
		case 1:
			$r = 'Nuh-uh uh!';
		break;
		case 2:
			$r = 'Thank you! Your ask has been submitted!';
		break;
	}
	
	return $r;
}
add_shortcode('askbox', 'askbox_shortcode');

// helper functions
// captcha array key, user answer, captcha array
function askbox_captcha($key, $answer, $captchas){
	if(array_key_exists($key, $captchas) && strtolower(trim($answer)) == strtolower(trim($captchas[$key]['a'])))
	   { return true; }
	else
	   { return false; }
}

// process a submitted ask
function submit_ask($ask){
	$name = $ask['url'] ? '<a href="'. $ask['url'] .'">'. $ask['name'] .'</a>' : $ask['name'];
	$msg = '<p class="ask-asker"><cite>'. $name .'</cite> asked:</p><blockquote class="ask-question">'. $ask['question'] .'</blockquote><p class="asl-answer">Answer here...</p>';
	
	$post = array(
		'post_content' => $msg,
		'post_title' => $ask['name'] .' asked...',
		'post_excerpt' => $ask['question'],
		'post_status' => 'draft',
		'post_type' => 'post',
		'tags_input' => array('askbox')
	);
	$r = wp_insert_post($post);
	if($r){
		// add the asker's details as post meta
		add_post_meta($r, '_askbox', $ask);
		
		// notify the site admin via email
		$txt = "$ask[name] asked:\n\n> $ask[question]\n\n This has been added as a draft post: ". get_site_url() ."/wp-admin/post.php?post=". $r ."&action=edit\n\nSubmitted by: ". $_SERVER['REMOTE_ADDR'] ." // ". $_SERVER ['HTTP_USER_AGENT'];
		wp_mail(get_option('admin_email'), 'Someone asked a question!', $txt);
		
		return true;
	} else { return false; }
}
