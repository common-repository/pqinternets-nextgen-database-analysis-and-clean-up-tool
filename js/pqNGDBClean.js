//Part of Wordpress plugin
//Plugin Name: pqInternet's NextGEN Database Analysis and Clean-up
//Plugin URI: http://www.pqInternet.com
jQuery( document ).ready( function() {
	jQuery( '#pqNGDBClean_Analyze_Button' ).click( function( e ) {
		var link = this;
		var nonce = jQuery( link ).attr( 'data-nonce' );

		var data = {
			action: 'pqNGDBClean_ajax_analyze_db',
			nonce: nonce
		}
		jQuery('#pq_t1').html('');
		jQuery(link).prop('value', 'Working...');
		jQuery('#pq_t2').html('<strong>Analyzing the database tables: This can take a long time... Do Not Reload Page...</strong>');
		jQuery(link).prop('disabled', true);
		// Post to the server
		jQuery.post( pqNGDBClean_params.ajaxurl, data, function( data ) {
			// Parse the XML response with jQuery
			var status = jQuery( data ).find( 'response_data' ).text();
			var message = jQuery( data ).find( 'supplemental message' ).text();
			var error = jQuery( data ).find( 'supplemental error' ).text();
			var orphans = jQuery( data ).find( 'orphans' ).text();
			// successful?
			switch(status) {
				case 'success':
					jQuery('#pq_t1').html(message);
					jQuery(link).prop('value', 'Analyze');
					jQuery(link).prop('disabled', false);
					jQuery('#pq_t2').html('');
					jQuery('#pqNGDBClean_Clean_Button').prop('disabled', false);				
					break;
				
				case 'repair':
					jQuery('#pq_t1').html(message);
					jQuery(link).prop('value', 'Analyze');
					jQuery(link).prop('disabled', false);
					jQuery('#pq_t2').html('<h2 style="color:red;">Table Repairs are Needed</h2><p>Repairs will be attempted on any tables that do not pass a table check. This process will take longer than a normal clean.</p><p><strong>DO NOT PROCEED IF YOU DO NOT HAVE A VALID AND CURRENT BACKUP!</strong></p>');
					jQuery('#pqNGDBClean_Clean_Button').prop('disabled', false);							
					break;
					
				case 'error':
					// An error occurred, alert an error message
					jQuery('#pq_t1').html(message);
					jQuery(link).prop('value', 'Analyze');
					jQuery(link).prop('disabled', false);	
					jQuery('#pq_t2').html('Error!');				
					break;
					
			}
			if (error != '') {
				alert( error );
			}
			if (orphans != 0) {
				alert( 'Orphaned records were found, you should make a backup and run the Clean operation by clicking the Clean button. MAKE A BACKUP FIRST!');
			}
		});
		// Prevent the default behavior for the link
		e.preventDefault();
	});

	jQuery( '#pqNGDBClean_Clean_Button' ).click( function( e ) {
		var link = this;
		var nonce = jQuery( link ).attr( 'data-nonce' );

		var data = {
			action: 'pqNGDBClean_ajax_clean_db',
			nonce: nonce
		}
		if (jQuery('#pqNGDBCheck1').prop("checked")) {
			jQuery('#pq_t1').html('');
			jQuery(link).prop('value', 'Working...');
			jQuery(link).prop('disabled', true);
			jQuery('#pq_t2').html('<strong>Cleaning and Optimizing the database tables: This can take a <em>VERY</em> long time... Do Not Reload Page...</strong>');
			jQuery('#pqNGDBClean_Analyze_Button').prop('disabled', true);

			// Post to the server
			jQuery.post( pqNGDBClean_params.ajaxurl, data, function( data ) {
				// Get the Status
				var status = jQuery( data ).find( 'response_data' ).text();
				// Get the Message
				var message = jQuery( data ).find( 'supplemental message' ).text();
				var error = jQuery( data ).find( 'supplemental error' ).text();
				// If we are successful...
				if( status == 'success' ) {
					//jQuery( link ).parent().after( '<p><strong>' + message + '</strong></p>').remove();
					jQuery('#pq_t1').html(message);
					jQuery(link).prop('value', 'Clean');
					jQuery(link).prop('disabled', true);
					jQuery('#pq_t2').html('');
					jQuery('#pqNGDBClean_Analyze_Button').prop('disabled', false);
				} else {
					// An error occurred, alert an error message
					jQuery('#pq_t1').html(message);
					jQuery(link).prop('value', 'Clean');
					jQuery(link).prop('disabled', true);	
					jQuery('#pq_t2').html( error );
					jQuery('#pqNGDBClean_Analyze_Button').prop('disabled', false);				
					//alert("error");
				}
				if (error != '') {
					alert( error );
				}
				else {
					alert( 'Finished, please carefully review the output.');
				}
			});
		}
		else {
			alert("You must read everything in red and check the checkbox that you agree and that you have a valid backup.");
		}
		// Prevent the default behavior for the link
		e.preventDefault();
	});	
});