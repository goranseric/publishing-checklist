<div class="misc-pub-section publishing-checklist">
	<h4><?php esc_html_e( 'Publishing Checklist', 'publishing-checklist' ); ?></h4>

	<div class="publishing-checklist-items-complete">
		<?php echo esc_html( sprintf( __( '%d of %d tasks complete', 'publishing-checklist' ), count( $completed_tasks ), count( $tasks ) ) ); ?>
		<progress value="<?php echo (int) count( $completed_tasks ); ?>" max="<?php echo (int) count( $tasks ); ?>"></progress>
	</div>

	<?php if ( $incomplete_required ) { ?>
	<p>
		<strong><?php esc_html_e( 'Not ready for publication.', 'publishing-checklist' ); ?></strong>
		<?php echo wp_sprintf( esc_html__( 'Incomplete required tasks: %l.', 'publishing-checklist' ),
			wp_list_pluck( $incomplete_required, 'label' ) ); ?>
	</p>
	<p class="description">
		<?php esc_html_e( 'Please correct these issues and submit for review before publishing.', 'publishing-checklist' ); ?>
	</p>
	<?php } ?>

	<a href="javascript:void(0);" class="publishing-checklist-show-list"><?php esc_html_e( 'Show List', 'publishing-checklist' ); ?></a>

	<div class="publishing-checklist-items" style="display:none;">
		<ul>
			<?php foreach ( $tasks as $id => $task ) : ?>
			<li title="<?php echo esc_attr( $task['explanation'] ); ?>" class="<?php
					echo esc_attr( $task['required'] ? 'required' : 'not-required' ); ?>">
				<?php if ( in_array( $id, $completed_tasks, true ) ) : ?>
					<span class="dashicons dashicons-yes"></span>
				<?php else : ?>
					<span class="dashicons dashicons-no-alt"></span>
				<?php endif; ?>
				<?php echo esc_html( $task['label'] ); ?></li>
			<?php endforeach; ?>
		</ul>
	</div>
	<a href="javascript:void(0);" class="publishing-checklist-hide-list" style="display:none;"><?php esc_html_e( 'Hide List', 'publishing-checklist' ); ?></a>
</div>
