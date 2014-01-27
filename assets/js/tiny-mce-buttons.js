(function($) {
    tinymce.create('tinymce.plugins.SBEShortcode', {
        init : function(ed, url) {
            ed.addButton('sbeform', {
                title : 'Subscribe By Email Form',
                image : url + '/../images/tinymceicon.png',
                onclick : function() {
                    sbe_tinymce.perform_request( 'load-ui' );
                    return false;
                }
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

    var sbe_tinymce = {
        in_request: false,
        perform_request: function( action ) {
            if (this.in_request)
                return;

            this.in_request = true;

            var jqXHR = jQuery.ajax(
                ajaxurl,
                {
                    type : 'POST',
                    data : {
                        action : 'display_sbe_shortcode_admin_form',
                        security : 'aaa',
                        sbe_action : action,
                        sbe_params : {},
                        current_post : 'whatever'
                    },
                    success : function( response ) {
                        sbe_tinymce.action_success( action, response, this );
                        sbe_tinymce.in_request = false;
                    },
                    error : function( jqXHR, textStatus, errorThrown ) {
                        //SynvedShortcode.actionFailed(action, params, errorThrown, this);
                        //SynvedShortcode.doingRequest = false;
                    }
                }
            );
            
            return jqXHR;
        },
        action_success: function ( action, response, request ) {
            tb_show( 'Subscribe By Email', '#TB_inline' );
            var tb = jQuery("#TB_window");
            if ( tb ) {
                var tb_content = tb.find('#TB_ajaxContent');
                tb_content.html(response);

                var color_inputs = tb_content.find('.sbe-color-field').wpColorPicker({});

                tb_content.find('#sbe-insert-shortcode').click(function (e) {
                    e.preventDefault();
                    
                    var code = '[subscribe-by-email-form';

                    // Background color
                    var bgcolor = '';
                    var bgcolortheme_input = tb_content.find( 'input[name=bgcolortheme]' );
                    var bgcolortheme = bgcolortheme_input.attr('checked');

                    if ( ! bgcolortheme ) {
                        bgcolor = tb_content.find( 'input[name=bgcolor]' ).val();
                        code += ' bgcolor="' + bgcolor + '"'
                    }
                    

                    // Text color
                    var textcolor = '';
                    var textcolortheme_input = tb_content.find( 'input[name=textcolortheme]' );
                    var textcolortheme = textcolortheme_input.attr('checked');

                    if ( ! textcolortheme ) {
                        bgcolor = tb_content.find( 'input[name=textcolor]' ).val();
                        code += ' textcolor="' + bgcolor + '"';
                    }

                    // Width
                    var width = '';
                    var widthauto_input = tb_content.find( 'input[name=widthauto]' );
                    var widthauto = widthauto_input.attr('checked');

                    if ( ! widthauto ) {
                        width = tb_content.find( 'input[name=width]' ).val();
                        code += ' width="' + width + '"';
                    }

                    // Center?
                    var center_input = tb_content.find( 'input[name=center]' );
                    var center = center_input.attr('checked');

                    if ( ! center ) {
                        center = tb_content.find( 'input[name=width]' ).val();
                        code += ' center="false"';
                    }

                    code += ']';
                    
                    if (tinyMCE.activeEditor != null && tinyMCE.activeEditor.selection.getSel() != null)
                    {
                        tinyMCE.activeEditor.selection.setContent(code);
                    }
                    else
                    {
                        jQuery('#content').insertAtCaret(code);
                    }
                    
                    tb_remove();
                    
                    return false;
                });
            }
        }
    }
})();