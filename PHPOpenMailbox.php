<?php
	/*
		PHP Mailbox reader
	*/

	class php_mailbox{

		public $connection; // Connection to imap server

		private $server	= NULL;
		private $username = NULL;
		private $password = NULL;
		private $port = NULL;
		Private $ssl = NULL;

		private $inbox; // Inbox
		private $msg_cnt; // Mail Counter
		private $boxes; // Mailbox list

		function __construct() {
			/*
				Things to do on creation
			*/
		}

		function __destruct() {
			/*
				Doomsday routine
			*/
		}
		
		function setup( $server, $username, $password, $port, $options='/imap2/tls' ) {
			/*
				Assign connection information
			*/

			$this->username	= $username; 	// Assign Username
			$this->password	= $password; 	// Assign Password
			$this->port = $port; 			// Assign Port Number
			$this->options = $options;		// Assign encyption type

			$this->server = "{" . $server . $options . "}"; 		// Assign Server

		}

		function connect() {
			/*
				Connect to the mail server
			*/

			$this->connection = imap_open(	$this->server, 
											$this->username, 
											$this->password );
											
			if ( $this->connection == false ) {
				echo "Failed to connect";
			}
		}

		function close() {
			/*
				Close the connection to the mailbox server
			*/

			$this->inbox = array();
			$this->msg_cnt = 0;

			imap_close( $this->connection );
		}
		
		function change_mailbox( $box ) {
			
			imap_reopen($this->connection, $this->boxes[$box]);

		}
		
		function get_mailboxes() {
			/*
				List mailboxes
			*/
			
			$this->boxes = imap_list( $this->connection, $this->server, '*' );	

			return $this->boxes;
		}
		
		function get_inbox( $page = 1, $perpage = 100, $getbody = false, $peek = false ) {
			/*
			
				$page = Page number you wish to return.
				$perpage = number of email you wish to return at anyone time
				$getbody(true/false) = if you wish to get the email body aswell
				$peek (true/false) = if you wish to peek at the email and not mark them as read
				
				read the inbox
			*/
			
			$in = array();
			$this->msg_cnt = imap_num_msg( $this->connection );

			if ( $this->msg_cnt > $perpage ) {
				$offset = (( $page - 1) * $perpage); // calculate start position
				if ( $offset < 1 ) { $offset = 1; }
				if ( $offset > $this->msg_cnt ) { return false; } // No More emails
				
				$end = ( $page * $perpage); // Calcuiate end
				if ( $end > $this->msg_cnt ) {
					$end = $this->msg_cnt; // Make sure we dont eceed the amount of emails
				}
				
			} else {
				$offset = 1;
				$end = $this->msg_cnt; // Calcuiate end
			}
			
			for( $idx = $offset; $idx < $end; $idx++ ) {
				
				$body = NULL;
				
				if ( $getbody ) {
					if ( $peek != false ){
						$peek = FT_PEEK;
					}
					echo "Getting body";
					$body = imap_body( $this->connection, $idx, $peek );
				}
				
				$in[] = array(
					'index'     => $idx,
					'header'    => imap_headerinfo( $this->connection, $idx ),
					'body'      => $body,
					'structure' => imap_fetchstructure( $this->connection, $idx )
				);
			}

			$this->inbox = $in;
			
			return $in;
		}
		
		function get_mail_body( $index, $peek = false ) {
			if ( $peek != false ){
				$peek = FT_PEEK;
			}
					
			$this->inbox[$index]["body"] = imap_body( $this->connection, $this->inbox[$index]["index"], $peek );
			
		}

		function get_mail( $index=NULL ) {
			/*
				get a specific message (
						1 = first email, 
						2 = second email, 
							etc.
					)
			*/
			
			if ( count( $this->inbox ) <= 0 ) {
				return array();
			} elseif ( ! is_null( $index ) && isset( $this->inbox[$index] ) ) {
				return $this->inbox[ $index ];
			}

			return $this->inbox[0];
		}

		function get_attachments ( $index ) {
			
			$attachments = array();
			
			$structure = $this->inbox[$index]["structure"]; // Get Mail Struction
			
			$id = $this->inbox[$index]["index"];
			
			if(isset($structure->parts) && count($structure->parts)) {

				for($i = 0; $i < count($structure->parts); $i++) {

					$attachments[$i] = array(
						'is_attachment' => false,
						'filename' => '',
						'name' => '',
						'attachment' => ''
					);
					
					if($structure->parts[$i]->ifdparameters) {
						foreach($structure->parts[$i]->dparameters as $object) {
							if(strtolower($object->attribute) == 'filename') {
								$attachments[$i]['is_attachment'] = true;
								$attachments[$i]['filename'] = $object->value;
							}
						}
					}
					
					if($structure->parts[$i]->ifparameters) {
						foreach($structure->parts[$i]->parameters as $object) {
							if(strtolower($object->attribute) == 'name') {
								$attachments[$i]['is_attachment'] = true;
								$attachments[$i]['name'] = $object->value;
							}
						}
					}
					
					if($attachments[$i]['is_attachment']) {
						if ( $this->inbox[$index]["body"] == NULL ) {
							$this->inbox[$index]["body"] = imap_fetchbody($this->connection, $id, $i+1);
						}

						$message = $this->inbox[$index]["body"];
						
						switch ( $structure->parts[$i]->encoding ) {
							case 0:
								$attachments[$i]['attachment'] = $message;
								break;
							case 1:
								$attachments[$i]['attachment'] = imap_8bit($message);
								break;
							case 2:
								$attachments[$i]['attachment'] = imap_binary($message);
								break;
							case 3:
								// BASE64
								$attachments[$i]['attachment'] = base64_decode($message);
								break;
							case 4:
								// QUOTED-PRINTABLE
								$attachments[$i]['attachment'] = quoted_printable_decode($message);
								break;
						}
					}
				}
				
				return $attachments;
			} else {
				return false;
			}
		}

		function move_mail( $index, $folder='INBOX.Processed' ) {
			/*
				Move email message to folder
			*/
			
			imap_mail_move( $this->connection, $index, $folder );
			imap_expunge( $this->connection );

			$this->get_inbox(); // Update the Inbox
		}

		function search_mail( $search ) {
			/*
				Search Mail Box
			*/

			return imap_search( $this->connection, $search);
		}
		
		function send_mail() {
			//imap_mail
		}
	}
?>