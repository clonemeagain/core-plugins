<?php 
/**
 * ClamAV Attachment Scanner
 *
 * Requires the fs-storage plugin.. or, I suppose, we could simply save attachments to a tmpnam file and scan them during fetch/parse.
 * 
 * Grizly.
 * 
 */

class ClamAVPluginConfig extends PluginConfig {
    function getOptions() {
        return array(
            'av_enabled' => new BooleanField(array(
                'id' => 'av_enabled',
                'label' => 'Enable the AntiVirus Engine?',
                'configuration' => array(
                    'desc' => 'Tick this to let us scan things!')
            )),
        	    'brk' => new SectionBreakField(array(
                'label' => 'Email Domain-name',
                'hint' => 'We are going to be generating a lot of random email addresses for our "Clients", best to specify which "domain-name" they should all be, we shouldn\'t be sending out notifications, but it might happen while testing, so best to use something innocuous and un-routable!',
            )),
            'host' => new TextboxField(array(
                'label' => 'unix pipe',
                'hint' => '(optional) Enter a custom pipe to interface with ClamD, ie: unix:///var/run/clamav/clamd.ctl ',
                'configuration' => array('size'=>40),
            )),
        	'port' => new TextboxField(array(
        		'label' => 'Port',
        		'hint' => 'If not using the unix pipe, enter the port number',
        		'configuration' => array('size'=>5),
        	)),
        	'timeout' => new TextboxField(array(
        		'label' => 'Timeout (s)',
        		'hint' => 'How long to wait for the pipe to return from scanning',
        		'configuration' => array('size'=>3),
        	)),
        );
    }

    function pre_save($config, &$errors) {
        $path = $config['av_enabled'];
        $field = $this->getForm()->getField('av_enabled');
		print_r($field);
		
		$host = $this->getForm()->getField('host');
    }
}

class ClamAVPlugin extends Plugin {
    var $config_class = 'ClamAVPluginConfig';

    function bootstrap() {
        $enabled = $this->getConfig()->get('av_enabled');
        if ($enabled) {
            //Admin has said we can create a mess of tickets.. best not to do this for every page load though, we simply 'Enable' it.
        }
    }
}

return array(
    'id' =>             'attach:av', # notrans
    'version' =>        '0.1',
    'name' =>           'Attachment Scanner, using ClamAV',
    'author' =>         'Grizly',
    'description' =>    'Creates an interface to ClamAV to allow attachments to be scanned for virii.',
    'url' =>            'http://www.osticket.com/plugins/',
    'plugin' =>         'ClamAVPlugin'
);