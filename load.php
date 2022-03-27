<?php
namespace TGMPA;

if (function_exists('add_action') && function_exists('did_action')) {
		if ( did_action( 'plugins_loaded' ) ) {
				TGMPA::activation();
		} else {
				add_action( 'plugins_loaded', ['\TGMPA\TGMPA', 'activation'] );
		}
}

