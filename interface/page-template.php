<p><strong><?php _e('Template', BUV_TEXTDOMAIN) ?></strong></p>
<label class="screen-reader-text" for="page_template"><?php _e('Page Template', BUV_TEXTDOMAIN) ?></label>
<select name="bu_page_template" id="page_template">
	<option value='default'><?php _e('Default Template', BUV_TEXTDOMAIN); ?></option>
	<?php page_template_dropdown($page_template); ?>
</select>
