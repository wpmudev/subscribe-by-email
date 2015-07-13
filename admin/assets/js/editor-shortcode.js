(function($) {
    tinymce.PluginManager.add( 'sbe_shortcodes', function ( editor ) {
        var ed = tinymce.activeEditor;

        editor.addButton( 'sbe_shortcodes', {
            icon: 'mce-i-sbe',
            title: ed.getLang( 'sbe_l10n.title' ),
            onclick: function () {
                editor.windowManager.open({
                    title: ed.getLang( 'sbe_l10n.title' ),
                    body: [
                        {
                            type:    'checkbox',
                            name:    'auto_opt_field',
                            label:   ed.getLang( 'sbe_l10n.auto_opt_field_title' ),
                            description:'dsfsdff'
                        }
                    ],
                    onsubmit: function ( e ) {
                        var auto_opt_field, default_view_field;
                        if ( e.data.auto_opt_field )
                            auto_opt_field = ' autopt="true"';
                        else
                            auto_opt_field = '';

                        editor.insertContent( '[subscribe-by-email-form' + auto_opt_field  +']' );
                    }
                });
            }

        });
    } );
 

})(jQuery);