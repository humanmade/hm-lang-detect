(function( $ ) {
    "use strict";

    $(function() {

        $( document ).on( 'heartbeat-send', function( e, data ) {
            data['client'] = 'hm_request_geoip_status';
        });

        $( document ).on( 'heartbeat-tick', function( e, data ) {

            if ( data['server'] === 'ready' ) {
                // if country has been determined and official lang != current,show notif
                $.post( hm_lang_data.ajaxurl, { action: 'ajax_render_notice' }, function( data ){
                    $( '.header .inner' ).prepend( data );
                });
            }

        });

    });

}( jQuery ));