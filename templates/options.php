<div class="wrap">
		<h2><?php _e( 'Twitter Mentions as Comments Options', 'twitter-mentions-as-comments' ); ?></h2>
		<form method="post" action='options.php' id="tmac_form">
	<?php settings_errors(); ?>
	<?php settings_fields( 'tmac_options' ); ?>
	<?php if ( $mentions ) self::$parent->template->load( 'forced-refresh' ); ?>
		<table class="form-table">
			<tr valign="top">
				<th scope="row"><label for="tmac_options[RTs]"><?php _e( 'Exclude ReTweets?', 'twitter-mentions-as-comments' ); ?></label></th>
				<td>
					<input name="tmac_options[RTs]" type="radio" id="tmac_options[RTs][0]" value="0" <?php if ( $options->RTs ) echo 'checked="checked"'; ?>/> <label for="tmac_options[RTs][0]"><?php _e( 'Include ReTweets', 'twitter-mentions-as-comments' ); ?></label><BR />
					<input name="tmac_options[RTs]" type="radio" id="tmac_options[RTs][1]" value="1" <?php if ( $options->RTs ) echo 'checked="checked"'; ?>/> <label for="tmac_options[RTs][1]"><?php _e( 'Exclude ReTweets', 'twitter-mentions-as-comments' ); ?></label><BR />
					<span class="description"><?php _e( 'If "Exclude ReTweets" is selected, ReTweets (both old- and new-style) will be ignored.', 'twitter-mentions-as-comments' ); ?></span>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row"><label for="tmac_options[posts_per_check]"><?php _e( 'Number of Posts to Check', 'twitter-mentions-as-comments' ); ?></label></th>
				<td>
					Check the <input type="text" name="tmac_options[posts_per_check]" id="tmac_options[posts_per_check]" value="<?php $options->posts_per_check; ?>" size="2"> <?php _e( 'most recent posts for mentions', 'twitter-mentions-as-comments' ); ?><br />
					<span class="description"><?php _e( 'If set to "-1", will check all posts, if blank will check all posts on your site\'s front page.', 'twitter-mentions-as-comments' ); ?></span>
				</td>
			</tr>		
			<tr valign="top">
				<th scope="row"><label for="tmac_options[comment_type]"><?php _e( 'Comment Type', 'twitter-mentions-as-comments' ); ?></label></th>
				<td>
					<select name="tmac_options[comment_type]" id="tmac_options[comment_type]">
						<option value=""<?php if ( $options->comment_type == '') echo ' SELECTED'; ?>><?php _e( 'Comment', 'twitter-mentions-as-comments' ); ?></option>
						<option value="trackback"<?php if ( $options->comment_type == 'trackback' ) echo ' SELECTED'; ?>><?php _e( 'Trackback', 'twitter-mentions-as-comments' ); ?></option>
						<option value="pingback"<?php if ( $options->comment_type == 'pingback' ) echo ' SELECTED'; ?>><?php _e( 'Pingback', 'twitter-mentions-as-comments' ); ?></option>
					</select><br />
					<span class="description"><?php _e( 'Most users will probably not need to change this setting, although you may prefer that Tweets appear as trackbacks or pingbacks if your theme displays these differently', 'twitter-mentions-as-comments' ); ?></span>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row"><label for="tmac_options[manual_cron]"><?php _e( 'Checking Frequency', 'twitter-mentions-as-comments' ); ?></label></th>
				<td>
					<input name="tmac_options[manual_cron]" type="radio" id="tmac_options[manual_cron][0]" value="0" <?php if ( !$options->manual_cron ) echo 'checked="checked"'; ?>/> <label for="tmac_options[manual_cron][0]"><?php _e( 'Hourly', 'twitter-mentions-as-comments' ); ?></label><BR />
					<input name="tmac_options[manual_cron]" type="radio" id="tmac_options[manual_cron][1]" value="1" <?php if ( $options->manual_cron ) echo 'checked="checked"'; ?>/> <label for="tmac_options[manual_cron][1]"><?php _e( 'Manually', 'twitter-mentions-as-comments' ); ?></label><BR />
					<span class="description"><?php _e( 'The plugin can check for Tweets hourly (default), or, if you have the ability to set up a <a href="http://en.wikipedia.org/wiki/Cron">cron job</a>, can check any any desired frequency.', 'twitter-mentions-as-comments' ); ?></span><BR />
					<span class="description" id="cron-details"><br /><?php echo sprintf( __( 'For manual checking, you must set a crontab to execute the file <code>%s/cron.php</code>. The exact command will depend on your server\'s setup. To run every 15 minutes, for example (in most setups), the command would be: <code>/15 * * * * php %s/cron.php</code> Please be aware that Twitter does have some <a href="http://dev.twitter.com/pages/rate-limiting">API limits</a>. The plugin will make one search call per post, and one users/show call for each new user it finds (to get the user\'s real name).', 'twitter-mentions-as-comments' ), dirname( __FILE__ ), dirname( __FILE__ ) ); ?></span>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row">Force Check</th>
				<td>
					<a href="?page=tmac_options&force_refresh=true"><?php _e( 'Check for New Tweets Now', 'twitter-mentions-as-comments' ); ?></a><br />
					<span class="description"><?php _e( 'Normally the plugin checks for new Tweets on its own. Click the link above to force a check immediately.', 'twitter-mentions-as-comments' ); ?></span>
				</td>
			</tr>
			<?php self::$parent->template->donate(); ?>			
		</table>
		<p class="submit">
	         <input type="submit" class="button-primary" value="<?php _e('Save Changes') ?>" />
		</p>
		</form>