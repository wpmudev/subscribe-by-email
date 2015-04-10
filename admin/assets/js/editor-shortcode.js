(function($) {
    tinymce.PluginManager.add( 'sbe_shortcodes', function ( editor ) {
        var ed = tinymce.activeEditor;

        editor.addButton( 'sbe_shortcodes', {
            icon: 'mce-i-sbe',
            title: ed.getLang( 'sbe_l10n.title' ),
            onclick: function () {
                editor.insertContent( '[subscribe-by-email-form]' );
            }
        });
    } );
 

})(jQuery);