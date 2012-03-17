<div class="updated fade">
			<p><?php _e( 'Tweets Refreshed!', 'twitter-mentions-as-comments' ); ?>
			<?php if ($mentions == 0) { ?>
				<?php _e( 'No Tweets found.', 'twitter-mentions-as-comments' ); ?>
			<?php } else { ?>
				<a href="edit-comments.php"><?php printf( _n( "<strong>%d</strong> tweet found.", "<strong>%d</strong> tweets found.", $mentions, 'twitter-mentions-as-comments' ), $mentions ); ?></a>.</p>
			<?php } ?>
</div>
