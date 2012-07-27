<p><strong><?php _e('Template') ?></strong></p>
<label class="screen-reader-text" for="page_template"><?php _e('Page Template') ?></label>
<select name="bu_page_template" id="page_template">
	<option value='default'><?php _e('Default Template'); ?></option>
	<?php page_template_dropdown($page_template); ?>
</select>
