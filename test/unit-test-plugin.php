<?php

class PluginTest extends TestCase
{
    public function test_plugin_installed() {
        activate_plugin( 'disciple-tools-import/disciple-tools-import.php' );

        $this->assertContains(
            'disciple-tools-import/disciple-tools-import.php',
            get_option( 'active_plugins' )
        );
    }
}
