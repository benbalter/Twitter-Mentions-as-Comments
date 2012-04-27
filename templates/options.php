<div class="wrap">
		<h2><?php _e( 'Twitter Mentions as Comments Options', 'twitter-mentions-as-comments' ); ?></h2>
		<form method="post" action='options.php' id="tmac_form">
	<?php settings_errors(); ?>
	<?php settings_fields( $this->parent->slug_ ); ?>
	<?php if ( isset( $_GET['force_refresh'] ) ) $this->parent->template->load( 'forced-refresh', compact( 'mentions' ) ); ?>
		<table class="form-table">
			<tr valign="top">
				<th scope="row"><label for="<?php echo  $this->parent->slug_; ?>[RTs]"><?php _e( 'Exclude ReTweets?', 'twitter-mentions-as-comments' ); ?></label></th>
				<td>
					<input name="<?php echo  $this->parent->slug_; ?>[RTs]" type="radio" id="<?php echo  $this->parent->slug_; ?>[RTs][0]" value="0" <?php if ( !$this->parent->options->RTs ) echo 'checked="checked"'; ?>/> <label for="<?php echo  $this->parent->slug_; ?>[RTs][0]"><?php _e( 'Include ReTweets', 'twitter-mentions-as-comments' ); ?></label><BR />
					<input name="<?php echo  $this->parent->slug_; ?>[RTs]" type="radio" id="<?php echo  $this->parent->slug_; ?>[RTs][1]" value="1" <?php if ( $this->parent->options->RTs ) echo 'checked="checked"'; ?>/> <label for="<?php echo  $this->parent->slug_; ?>[RTs][1]"><?php _e( 'Exclude ReTweets', 'twitter-mentions-as-comments' ); ?></label><BR />
					<span class="description"><?php _e( 'If "Exclude ReTweets" is selected, ReTweets (both old- and new-style) will be ignored.', 'twitter-mentions-as-comments' ); ?></span>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row"><label for="<?php echo  $this->parent->slug_; ?>[posts_per_check]"><?php _e( 'Number of Posts to Check', 'twitter-mentions-as-comments' ); ?></label></th>
				<td>
					<?php printf( __( 'Check the %s most recent posts for mentions', 'twitter-mentions-as-comments' ), '<input type="text" name="' . $this->parent->slug_ . '[posts_per_check]" id="' . $this->parent->slug_ . '[posts_per_check]" value="' . $this->parent->options->posts_per_check . '" size="2">' ) ; ?><br />
					<span class="description"><?php _e( 'If set to "-1", will check all posts, if blank will check all posts on your site\'s front page.', 'twitter-mentions-as-comments' ); ?></span>
				</td>
			</tr>		
			<tr valign="top">
				<th scope="row"><label for="<?php echo  $this->parent->slug_; ?>[comment_type]"><?php _e( 'Comment Type', 'twitter-mentions-as-comments' ); ?></label></th>
				<td>
					<select name="<?php echo  $this->parent->slug_; ?>[comment_type]" id="<?php echo  $this->parent->slug_; ?>[comment_type]">
						<option value=""<?php if ( $this->parent->options->comment_type == '') echo ' SELECTED'; ?>><?php _e( 'Comment', 'twitter-mentions-as-comments' ); ?></option>
						<option value="trackback"<?php if ( $this->parent->options->comment_type == 'trackback' ) echo ' SELECTED'; ?>><?php _e( 'Trackback', 'twitter-mentions-as-comments' ); ?></option>
						<option value="pingback"<?php if ( $this->parent->options->comment_type == 'pingback' ) echo ' SELECTED'; ?>><?php _e( 'Pingback', 'twitter-mentions-as-comments' ); ?></option>
					</select><br />
					<span class="description"><?php _e( 'Most users will probably not need to change this setting, although you may prefer that Tweets appear as trackbacks or pingbacks if your theme displays these differently', 'twitter-mentions-as-comments' ); ?></span>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row"><label for="<?php echo  $this->parent->slug_; ?>[manual_cron]"><?php _e( 'Checking Frequency', 'twitter-mentions-as-comments' ); ?></label></th>
				<td>
					<input name="<?php echo  $this->parent->slug_; ?>[manual_cron]" type="radio" id="<?php echo  $this->parent->slug_; ?>[manual_cron][0]" value="0" <?php if ( !$this->parent->options->manual_cron ) echo 'checked="checked"'; ?>/> <label for="<?php echo  $this->parent->slug_; ?>[manual_cron][0]"><?php _e( 'Hourly', 'twitter-mentions-as-comments' ); ?></label><BR />
					<input name="<?php echo  $this->parent->slug_; ?>[manual_cron]" type="radio" id="<?php echo  $this->parent->slug_; ?>[manual_cron][1]" value="1" <?php if ( $this->parent->options->manual_cron ) echo 'checked="checked"'; ?>/> <label for="<?php echo  $this->parent->slug_; ?>[manual_cron][1]"><?php _e( 'Manually', 'twitter-mentions-as-comments' ); ?></label><BR />
					<span class="description"><?php _e( 'The plugin can check for Tweets hourly (default), or, if you have the ability to set up a <a href="http://en.wikipedia.org/wiki/Cron">cron job</a>, can check any any desired frequency.', 'twitter-mentions-as-comments' ); ?></span><BR />
					<span class="description" id="cron-details"><br /><?php echo sprintf( __( 'For manual checking, you must set a crontab to execute the file <code>%s/cron.php</code>. The exact command will depend on your server\'s setup. To run every 15 minutes, for example (in most setups), the command would be: <code>/15 * * * * php %s/cron.php</code> Please be aware that Twitter does have some <a href="http://dev.twitter.com/pages/rate-limiting">API limits</a>. The plugin will make one search call per post, and one users/show call for each new user it finds (to get the user\'s real name).', 'twitter-mentions-as-comments' ), dirname( dirname( __FILE__ ) ), dirname( dirname( __FILE__ ) ) ); ?></span>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row"><?php _e( 'Force Check', 'twitter-mentions-as-comments' ); ?></th>
				<td>
					<a href="<?php echo esc_url( add_query_arg( 'force_refresh', true ) ); ?>"><?php _e( 'Check for New Tweets Now', 'twitter-mentions-as-comments' ); ?></a><br />
					<span class="description"><?php _e( 'Normally the plugin checks for new Tweets on its own. Click the link above to force a check immediately.', 'twitter-mentions-as-comments' ); ?></span>
				</td>
			</tr>
			<?php $this->parent->template->donate(); ?>			
		</table>
		<p class="submit">
	         <input type="submit" class="button-primary" value="<?php _e('Save Changes') ?>" />
		</p>
		</form>