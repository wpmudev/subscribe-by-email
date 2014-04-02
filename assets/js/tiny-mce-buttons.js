(function($) {
    tinymce.create('tinymce.plugins.SBEShortcode', {
        init : function(ed, url) {
            ed.addButton('sbeform', {
                cmd : 'insert_sbe_shortcode',
                title: sbe_l10n.title,
                image : sbe_l10n.png_icon
            });

            ed.addCommand('insert_sbe_shortcode', function() {
                tinymce.execCommand('mceInsertContent', false, '[subscribe-by-email-form]');
                //var selected_text = ed.selection.getContent();
                //var return_text = '';
                //return_text = '[mailchimp-form]';
                //ed.execCommand('mceInsertContent', 0, return_text);
            });
        },
        createControl : function(n, cm) {
            return null;
        },
        getInfo : function() {
            return {};
        }
    });
 
    // Register plugin
    tinymce.PluginManager.add( 'sbeshortcode', tinymce.plugins.SBEShortcode );

})();