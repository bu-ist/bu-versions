<div class="wrap">
	<?php screen_icon(); ?>
	<h2>Add Group</h2>
	<div class="form-wrap">
		<form method="POST">
			<input type="hidden" name="action" value="update"/>
			<label for="bu_group_name">Name</label><input name="name" id="bu_group_name" type="text" value="<?php echo esc_attr($group->get_name()); ?>"/>
			<label for="bu_group_description">Description</label><textarea name="description" id="bu_group_description"><?php echo esc_textarea($group->get_description()); ?></textarea>
			<h3>Users</h3>
			<?php BU_Groups_Admin::user_checkboxes($group->users); ?>

			<h3>Sections</h3>

			<input class="button-primary" type="submit" value="Update Group">
		</form>
	</div>

</div>

