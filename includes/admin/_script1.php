<script type="text/javascript">

    jQuery(document).ready(function(){
        getAllDefaultValues();
    });

    function check_column_mappings(id){
        //console.log('check_column_mappings');
        var elements, selected, selectedValue, c; 

        selected = document.getElementById('csv_mapper_'+id);
        selectedValue = selected.options[selected.selectedIndex].value;

        //console.log('selected_value='+selectedValue);
        elements = document.getElementsByClassName('cf-mapper');
        for(var i=0; i<elements.length; i++){
            if(i!=id && selectedValue==elements[i].value){
                //console.log('IND:' + i + ' ID:' + elements[i].id + ' VALUE:' + elements[i].value);
                selected.selectedIndex = 'IGNORE';
                if(elements[i].value!='IGNORE'){
                    alert('Already Mapped!');
                }                       
            }
        }
    }            

    function getAllDefaultValues(){                
        jQuery('.mapper-table tbody > tr.mapper-coloumn').each(function(){
            //console.log('C:'+jQuery(this).attr('data-row-id'));
            var i = jQuery(this).attr('data-row-id');
            if(typeof i !== 'undefined'){ getDefaultValues(i); }
        });                
    }

    function getDefaultValues2(id){ 
        alert('Checkpoint');

    }

    function getDefaultValues(id){                

        var selected, selectedValue, dom, ty, hlp;
        selected = document.getElementById('csv_mapper_'+id);
        selectedValue = selected.options[selected.selectedIndex].value;

        jQuery('.helper-fields').hide().html(''); 
        //jQuery

        //console.log('id:' + id + ' v:'+ selectedValue);

        //hlp = document.getElementById('helper-fields-'+selectedValue+'-txt').innerHTML;
        //document.getElementById('helper-fields-'+id).innerHTML = hlp;

        dom = jQuery('#helper-fields-'+selectedValue+'-txt');                
        ty = dom.attr('data-type');

        if(ty == 'key_select' || ty == 'multi_select'){
            hlp = dom.html(); //console.log('hlp:' + hlp);

            jQuery('#unique-values-'+id).show();
            //jQuery('#unique-values-'+id).find('.selected-mapper-column-name').html( jQuery('#csv_mapper_'+id).val() );
            jQuery('#unique-values-'+id).find('.selected-mapper-column-name').html( jQuery('#csv_mapper_'+id+' option:selected').text() );
            jQuery('#helper-fields-'+id).html( hlp ); //.show();

            jQuery('.value-mapper-'+id).html('');

            jQuery('.value-mapper-'+id).append('<option value="">--select-one--</option>');

            //h_sel = jQuery('.value-mapper-'+id).attr('data-value');

            //default-value-options
            jQuery.each( dom.find('.default-value-options li'), function(i,v){
                var h_this, h_value, h_label, h_html, h_sel;
                h_this = jQuery(this);
                h_value = h_this.find('.hlp-value').html();
                h_label = h_this.find('.hlp-label').html();
                if(!h_label.length>0){ h_label = h_value.toUpperCase(); }
                //console.log('id:' +i+' value:'+h_value+' label:'+h_label);                        


                h_html = '<option value="'+h_value+'"'; 
                //if(h_sel==h_value){ h_html = h_html + ' selected="selected"'; }
                h_html = h_html + '>'+h_label+'</option>';

                jQuery('.value-mapper-'+id).append(h_html);
            });

            jQuery('.value-mapper-'+id).each(function(){
                h_sel = jQuery(this).attr('data-value');

                jQuery(this).find('option').each(function(){
                    if(h_sel==jQuery(this).attr('value')){
                        jQuery(this).attr('selected','selected');
                    }
                });

            });

        } else {
            jQuery('#unique-values-'+id).hide();
        }
    }

</script>