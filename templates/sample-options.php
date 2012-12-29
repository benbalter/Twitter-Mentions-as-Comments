<?php
/**
 * Provides example options template
 * @project Plugin_Boilerplate
 * @subproject Hello_Dolly2
 */
?>
<div class="wrap">
	<h2><?php _e( 'Hello Dolly Options' ); ?></h2>
	<form method="post" action='options.php' id="hd2_form">
		<?php settings_errors(); ?>
		<?php settings_fields( $this->parent->slug_ ); ?>
	<table class="form-table">
		<tr valign="top">
			<th scope="row">
				<label for="lyrics"><?php _e( 'Lyrics' ); ?></lyrics>
			</th>
			<td>
				<textarea name="<?php echo $this->parent->slug_; ?>[lyrics]" id="lyrics" style="width: 400px; height: 600px;"><?php echo implode( "\n", $this->parent->options->lyrics ); ?></textarea>
			</td>
		</tr>
	<?php $this->parent->template->donate(); ?>
	</table>
	<p class="submit">
	         <input type="submit" class="button-primary" value="<?php _e('Save Changes') ?>" />
	         <a href="<?php echo esc_url( add_query_arg( 'hd2_reset', true ) ); ?>" class="button">Reset to original</a>
	</p>
	</form>
</div>