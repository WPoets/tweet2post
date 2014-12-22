<?php
/**
 * WordPress Cron Implementation for hosts, which do not offer CRON or for which
 * the user has not set up a CRON job pointing to this file.
 *
 * The HTTP request to this file will not slow down the visitor who happens to
 * visit when the cron job is needed to run.
 *
 * @package WordPress
 */

ignore_user_abort(true);

if ( !empty($_POST) || defined('DOING_AJAX') || defined('DOING_CRON') )
	die();

/**
 * Tell WordPress we are doing the CRON task.
 *
 * @var bool
 */
define('DOING_CRON', true);

if ( !defined('ABSPATH') ) {
	/** Set up WordPress environment */
	require_once( dirname( __FILE__ ) . '/wp-load.php' );
}
require_once('TwitterAPIExchange.php');
require_once(ABSPATH . 'wp-admin/includes/media.php');
require_once(ABSPATH . 'wp-admin/includes/file.php');
require_once(ABSPATH . 'wp-admin/includes/image.php');

$key='';  //Consumer Key (API Key)
$secret =''; //Consumer Secret (API Secret)
$token='';  //Access Token
$token_sceret='';  //Access Token Secret
$embeddly_key = "";  //embeddly api key

$twitter_accounts =array('rohit11','LHInsights','trakin');

$settings = array(
    'oauth_access_token' => $token,
    'oauth_access_token_secret' => $token_sceret,
    'consumer_key' => $key,
    'consumer_secret' => $secret
);

/** Perform a POST request and echo the response **/
$twitter = new TwitterAPIExchange($settings);

//now get the tweets// save the last_tweet_id
$last_tweet_id=array();
foreach($twitter_accounts as $t_acc)
{
	$url = 'https://api.twitter.com/1.1/statuses/user_timeline.json';
	$sc=get_option('since_last_time');
	if($sc)
	{
		$since_id ='&since_id='.$sc[$t_acc];
	}
	else
		$since_id ='';
	
	$getfield = '?screen_name='.$t_acc.'&count=100'.$since_id;
	
	$requestMethod = 'GET';	
	//call twitter to get the tweets,
	$response= $twitter->setGetfield($getfield)->buildOauth($url, $requestMethod)->performRequest();
	//foreach tweet parse the urls
	$decoded_response=json_decode($response);
	// from each url fetch title, one para and one image2wbmp
	foreach($decoded_response as $t)
	{
		$last_tweet_id[$t_acc]=$t->id_str;
		foreach($t->entities->urls as $url)
		{
			echo '<br>'.$url->expanded_url;
			$r=wp_remote_get('http://api.embed.ly/1/oembed?key='.$embeddly_key.'&url='.$url->expanded_url.'&maxwidth=500');
			if(!is_wp_error($r))
			{ 
				$r_decoded=json_decode($r['body']);
				//create a post, with title and para
				// Create post object
					$my_post = array(
					'post_title' => $r_decoded->title,
					'post_content'  => $r_decoded->description,
					'post_status'   => 'publish',
					'post_author'   => 1
					);

					// Insert the post into the database
					$post_id=wp_insert_post($my_post);
				//download the image and make it featured image if we have 1 or more image
				if(isset($r_decoded->images) && strlen($r_decoded->images)>=1)
				{	
					print_r($r_decoded->images);
					$image=$r_decoded->images[0]->url;
				
					// magic sideload image returns an HTML image, not an ID
					$media = media_sideload_image($image, $post_id);

					// therefore we must find it so we can set it as featured ID
					if(!empty($media) && !is_wp_error($media)){
						$args = array(
							'post_type' => 'attachment',
							'posts_per_page' => -1,
							'post_status' => 'any',
							'post_parent' => $post_id
						);

						// reference new image to set as featured
						$attachments = get_posts($args);

						if(isset($attachments) && is_array($attachments)){
							foreach($attachments as $attachment){
								// grab source of full size images (so no 300x150 nonsense in path)
								$image = wp_get_attachment_image_src($attachment->ID, 'full');
								// determine if in the $media image we created, the string of the URL exists
								if(strpos($media, $image[0]) !== false){
									// if so, we found our image. set it as thumbnail
									set_post_thumbnail($post_id, $attachment->ID);
									// only want one image
									break;
								}
							}
						}
					}
				}	
				update_post_meta($post_id,'orignal_url',$r_decoded->url);
			}	
		}
	}
	
	//save the since_id parameter
}
//add_option('since_last_time',$last_tweet_id);
die();
