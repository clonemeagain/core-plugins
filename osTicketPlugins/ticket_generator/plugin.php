<?php 
/**
 * Ticket Generator Plugin
 *
 * Creates a WHOLE MESS OF TICKETS.. best not play with this on a prod server.. ;-)
 * 
 * Inspired by the devel module of Drupal.. and the need to manually create tickets for testing things.. BOOORINNG!!
 * Also, I needed a plugin to play with.
 * 
 * Big ups to http://tinsology.net/scripts/php-lorem-ipsum-generator/
 */



class TicketGeneratorPluginConfig extends PluginConfig {
    function getOptions() {
        return array(
            'generator_enabled' => new BooleanField(array(
                'id' => 'generator_enabled',
                'label' => 'Enable the Generator Functions?',
                'configuration' => array(
                    'desc' => 'Tick this to let us create things!')
            )),
        	    'brk' => new SectionBreakField(array(
                'label' => 'Email Domain-name',
                'hint' => 'We are going to be generating a lot of random email addresses for our "Clients", best to specify which "domain-name" they should all be, we shouldn\'t be sending out notifications, but it might happen while testing, so best to use something innocuous and un-routable!',
            )),
            'domain' => new TextboxField(array(
                'label' => 'Domain Name.',
                'hint' => '(optional) Domain name we will be creating, otherwise, we use ticketguy.local',
                'configuration' => array('size'=>40),
            )),
        );
    }

    function pre_save($config, &$errors) {
        $path = $config['generator_enabled'];
        $field = $this->getForm()->getField('generator_enabled');
		print_r($field);
    }
}

class TicketGeneratorPlugin extends Plugin {
    var $config_class = 'TicketGeneratorPluginConfig';

    function bootstrap() {
        $enabled = $this->getConfig()->get('generator_enabled');
        if ($enabled) {
            //Admin has said we can create a mess of tickets.. best not to do this for every page load though, we simply 'Enable' it.
        }
    }
}

return array(
    'id' =>             'devel:gen', # notrans
    'version' =>        '0.1',
    'name' =>           'Ticket Generator for Development',
    'author' =>         'Grizly',
    'description' =>    'Creates lots of random tickets for testing settings/permissions/things.',
    'url' =>            'http://www.osticket.com/plugins/',
    'plugin' =>         'TicketGeneratorPlugin'
);