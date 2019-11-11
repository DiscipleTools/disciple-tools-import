<?php ?>
<script type="text/javascript">
var pid = 1000;
function process( q, num, fn, done ) {
    // remove a batch of items from the queue
    var items = q.splice(0, num),
        count = items.length;

    // no more items?
    if ( !count ) {
        // exec done callback if specified
        done && done();
        // quit
        return;
    }

    // loop over each item
    for ( var i = 0; i < count; i++ ) {
        // call callback, passing item and
        // a "done" callback
        fn(items[i], function() {
            // when done, decrement counter and
            // if counter is 0, process next batch
            --count || process(q, num, fn, done);
            pid++;
        });                    

    }
}

// a per-item action
function doEach( item, done ) {                
    console.log('starting ...' ); //t('starting ...');
    jQuery.ajax({
        type: "POST",
        data: item,
        contentType: "application/json; charset=utf-8",
        dataType: "json",
        url: "<?php echo esc_url_raw( rest_url() ); ?>" + `dt/v1/contact/create?silent=true`,
        beforeSend: function(xhr) {
            xhr.setRequestHeader('X-WP-Nonce', "<?php /*@codingStandardsIgnoreLine*/ echo sanitize_text_field( wp_unslash( wp_create_nonce( 'wp_rest' ) ) ); ?>");
        },
        success: function(data) {
            console.log('done'); t('PID#'+pid+' done');
            //jQuery('#contact-links').append('<li><a href="'+data.permalink+'" target="_blank">Contact #'+data.post_id+'</a></li>');
            done();
        },
        error: function(xhr) { // if error occured
            alert("Error occured.please try again");
            console.log("%o",xhr);
            t('PID#'+pid+' Error occured.please try again');
        }
    });
}

// an all-done action
function doDone() {
    console.log('all done!'); t('all done');
    jQuery("#back").show();
}

function t(m){
    var el, v;
    el = document.getElementById("import-logs");
    v = el.innerHTML;
    v = v + '<br/>' + m;
    el.innerHTML = v;                
}

function reset(){
    document.getElementById("import-logs").value = '';
}
</script>