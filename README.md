# PHPOpenMailbox

Early Alpha

Usage

		$mymail = new php_mailbox; // Create New Mailbox class
		
		$mymail->setup( $server, $username, $password, $rport, $options); // Setup connection details
		$mymail->connect(); // Connect to mailbox
		
		$msgCount = $mymail->getMailCount();
		
		$mailboxes = $mymail->listMailboxes(); // Get list of mailboxes
    
  		$mymail->getMailbox(); // Get mailbox contents
