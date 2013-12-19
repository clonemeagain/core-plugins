<?php 
/**
 * Pin Tickets Plugin
 */

class TicketPinPluginConfig extends PluginConfig {
    function getOptions() {
        return array(
            'pin_tickets_enabled' => new BooleanField(array(
                'id' => 'pin_tickets_enabled',
                'label' => 'Enable the Pinning of Tickets?',
                'configuration' => array(
                    'desc' => 'Tick this to let staff pin things!')
            )),
        );
    }

    function pre_save($config, &$errors) {
        $config['pin_tickets_enabled']= $this->getForm()->getField('pin_tickets_enabled');
		print_r($config);
    }
}

class TicketPinPlugin extends Plugin {
    var $config_class = 'TicketPinPluginConfig';

    function bootstrap() {
        $enabled = $this->getConfig()->get('pin_tickets_enabled');
        if ($enabled) {
            //Admin has said we can create a mess of tickets.. best not to do this for every page load though, we simply 'Enable' it.
        }
    }
}

return array(
    'id' =>             'ticket:pin', # notrans
    'version' =>        '0.1',
    'name' =>           'Pin Tickets',
    'author' =>         'Grizly',
    'description' =>    'Allows Tickets to be Pinned to top of list',
    'url' =>            'http://www.osticket.com/plugins/',
    'plugin' =>         'TicketPinPlugin'
);