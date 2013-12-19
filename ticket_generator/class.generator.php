<?php
/**
 * Creates loads of random tickets for "testing" purposes..
 * 
 * Hard to use a dev environment without a few tickets to play with. This enumerates all possible help-topics, departments and
 * such, and creates a few dozen tickets for each. With random attachments.
 * 
 * It then "closes" a few of them, assigns a few between existing staff and generally "populates" the database, but uses osTickets calls to do so.
 * 
 * Ideally, you would simply throw it at a mailbox and have it generate a shit-tonne of tickets, but this works too.
 * 
 * LoremIpsum generator? http://tinsology.net/scripts/php-lorem-ipsum-generator/ Oh Yeah!
 */
require_once 'LoremIpsum.class.php';
trait MakesText {
	use MakesNumbers;
	/**
	 * Uses the LoremIpsum class to generate random text, of an indeterminate length.
	 *
	 * @return Ambigous <string, multitype:multitype:Ambigous <> >
	 */
	function randomText() {
		$text = new LoremIpsumGenerator (); // txt,plain,html
		return $text->getContent ( $this->randomNumber ( 30, 200 ), 'txt' );
	}
	function randomHtml() {
		$text = new LoremIpsumGenerator (); // txt,plain,html
		return $text->getContent ( $this->randomNumber ( 30, 200 ), 'html' );
	}
	function randomGibberish($length = 20) {
		$nps = "";
		for($i = 0; $i < $length; $i ++) {
			$nps .= chr ( (mt_rand ( 1, 36 ) <= 26) ? mt_rand ( 97, 122 ) : mt_rand ( 48, 57 ) );
		}
		return $nps;
	}
}
trait BooleanOption {
	/**
	 * Create a boolean value of indeterminate state.
	 *
	 * @return boolean either true or false.
	 */
	function randomBoolean() {
		return mt_rand ( 0, 1 ) == 1;
	}
}
trait MakesNumbers {
	/**
	 * Creates a random number
	 *
	 * @param number $lower        	
	 * @param number $upper        	
	 * @return number
	 */
	function randomNumber($lower = 0, $upper = 10000) {
		return mt_rand ( $lower, $upper );
	}
}
class Generated_Ticket extends Ticket {
}
class Generated_Client extends Client {
}
abstract class Generator {
	var $num,$type;
	function __construct() {
		global $ost;
		
		$this->num = filter_input('get','num',FILTER_SANITIZE_NUMBER_INT);
		$this->type = filter_input('get','type',FILTER_SANITIZE_STRING);
		
		if ($ost->getConfig()->get('generator_enabled') && is_numeric($this->num) && strlen($this->type) > 1) {
			// Begin generating
			$this->generate ();
		} else {
			// display text-form to choose the volume & type to generate.
			$this->showForm ();
		}
	}
	abstract function generate();
	abstract function showForm();
}
class Ticket_Generator extends Generator {	
	use MakesText;
	// Make sure that NOTIFICATIONS ARE OFF.. also, we are going to be generating a lot of random email addresses.
	// Probably best to specify the domain-name we want to abuse in config.
	function __construct() {
		super::__construct ();		
	}
	function generate() {		
		if(!$this->type == 'ticket')
			throw new Exception('Constructor called incorrectly!');
	}
	function showForm() {
	}
}
class Client_Generator extends Generator {

	function __construct() {
		super::__construct ();
	}
	function generate() {
		global $ost;
		if(!$this->type == 'client')
			throw new Exception('Constructor called incorrectly!');
		
		for ($i = 0; $i<=$this->num; $i++){
			
			//Need to make an email address, test it for validity, if it is unique, make a client.
			$email = $this->randomGibberish($this->randomNumber(5,128));
			$email = $email . $ost->getConfig()->get('generator_domain'); //defaults to 'thisguy.local'
			new Generated_Client(false,$email);
		}
	}
	function showForm() {
		
	}
	
}