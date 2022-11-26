jQuery( document ).on( 'click', '.pt-claps-applause', function() {
	var $this 	= jQuery(this),
		post_id = $this.find('.claps-button').attr('data-id'),
		nonce 	= $this.find('#_wpnonce').val();

		// wp_claps_applause_ids = jQuery.cookie('wp_claps_applause_ids') ? jQuery.cookie('wp_claps_applause_ids').split(',') : [];
		wp_claps_applause_ids = Cookies.get('wp_claps_applause_ids') ? Cookies.get('wp_claps_applause_ids').split(',') : [];

	if ( $this.hasClass( 'has_rated' ) ) {
        return false;
    }

    var count = $this.find('.claps-count').text();

    jQuery( '#claps-count-'+post_id ).text( parseFloat(count) + 1 );
    jQuery( '#pt-claps-applause-'+post_id ).addClass( 'has_rated' );
	jQuery( '#pt-claps-applause-'+post_id ).find('.claps-button').text( clapsapplause.lovedText ).removeAttr( 'href' );
	
	jQuery.ajax({
		url : clapsapplause.ajax_url,
		type : 'POST',
		dataType: 'json',
		data : {
			action : 'pt_claps_applause',
			post_id : post_id,
			_wpnonce : nonce
		},
		success : function( data ) {		
			if ( data.status ) {
				wp_claps_applause_ids.push( post_id );
				// jQuery.cookie('wp_claps_applause_ids', wp_claps_applause_ids, {expires: 3});
				Cookies.set('wp_claps_applause_ids', wp_claps_applause_ids.toString(), { expires: 3, path: '' });
			} else {
				jQuery( '#pt-claps-applause-'+post_id ).removeClass( 'has_rated' );
				jQuery( '#pt-claps-applause-'+post_id ).find('.claps-button').text( clapsapplause.loveText );
				jQuery( '#claps-count-'+post_id ).text( count );
			}
		},
		error: function() {
			jQuery( '#pt-claps-applause-'+post_id ).removeClass( 'has_rated' );
			jQuery( '#pt-claps-applause-'+post_id ).find('.claps-button').text( clapsapplause.loveText );
			jQuery( '#claps-count-'+post_id ).text( count );
      	}
	});
	 
	return false;
})