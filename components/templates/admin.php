<div class="wrap">
	<h2>Copy Layout</h2>
	<div class="content-container">
		<h3>Current Layout (Read Only)</h3>
		<p>Copy this block to save the current layout for archiving or applying to another blog.</p>
		<textarea cols="100" rows="15"><?php echo esc_textarea( $current ); ?></textarea><br/>

		<h3>Replace Layout</h3>
		<p>Paste a saved layout here to override the current layout.</p>
		<form action="themes.php?page=copy-layout" method="post">
			<?php wp_nonce_field( 'go-copylayout-override', '_go_copylayout_override_nonce' ); ?>
			<textarea cols="100" rows="15" name="layout"></textarea><br/>
			<p class="submit">
				<input type="submit" value="Save" class="button-primary"/>
			</p>
		</form>
	</div>
</div>
