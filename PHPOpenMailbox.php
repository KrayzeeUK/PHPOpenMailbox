<?php
	/*
		PHP Mailbox reader
	*/

	class php_mailbox {

		public $connection; // Connection to imap server

		private $server = NULL;
		private $username = NULL;
		private $password = NULL;
		private $port = NULL;
		private $ssl = NULL;
		private $options = NULL;

		private $mailBox;            // Mailbox Contents
		private $mailCount;            // Email Message Counter
		private $boxes;                // Mailbox folder list
		private $currentMailbox;    // The currently selected mailbox Name

		public $lastErrorMessage;

		/**
		 *  Things to do on creation
		 */
		function __construct() {
		}

		/**
		 *  Doomsday routine
		 */
		function __destruct() {
		}

		/**
		 * Assign connection information
		 *
		 * @param string $server
		 * @param string $username
		 * @param string $password
		 * @param int    $port
		 * @param string $options
		 */
		public function setup( string $server, string $username, string $password, int $port, string $options = '/imap2/tls' ): void {

			$this->username = $username;                    // Assign Username
			$this->password = $password;                    // Assign Password
			$this->port = $port;                            // Assign Port Number
			$this->options = $options;                        // Assign encryption type
			$this->server = "{" . $server . $options . "}";    // Assign Server
		}

		/**
		 *  Connect to the mail server
		 */
		function connect(): bool {

			$this->connection = imap_open(
				$this->server,
				$this->username,
				$this->password
			);

			if ( $this->connection == FALSE ) {
				return FALSE;
			}
			return TRUE;
		}

		/**
		 * Close the connection to the mailbox server
		 */
		public function close(): void {

			$this->mailBox = array();
			$this->mailCount = 0;

			imap_close( $this->connection );
		}

		/**
		 * @param string $box Name of the box you wish to use
		 */
		public function changeMailbox( string $box ): bool {
			$this->currentMailbox = $this->boxes[ $box ];
			if ( imap_reopen( $this->connection, $this->boxes[ $box ] ) ) {
				return TRUE;
			} else {
				$this->lastErrorMessage = "Failed to change Mailbox";
				return FALSE;
			}
		}

		/**
		 * gets the current amount of messages in the mailbox
		 *
		 * @return false|int
		 */
		public function countMail() {
			$this->mailCount = imap_num_msg( $this->connection );

			return $this->mailCount;
		}

		/**
		 * Get and decode attachments from selected email
		 *
		 * @param int $index
		 * @return array
		 */
		public function getAttachments( int $index ): array {

			$attachmentCount = 0; // set initial value
			$attachments = array(); // initialise array

			if ( !empty( $this->mailBox[ $index ] ) ) {
				$structure = $this->mailBox[ $index ]["structure"]; // Get Mail Structure
				$id = $this->mailBox[ $index ]["index"];

				// check is any attachments
				if ( isset( $structure->parts ) && count( $structure->parts ) ) {
					for ( $i = 0; $i < count( $structure->parts ); $i++ ) {
						if ( !empty( $structure->parts[ $i ]->disposition ) ) {

							$attachments[ $attachmentCount ] = array(
								'is_attachment' => FALSE,
								'filename' => '',
								'name' => '',
								'attachment' => ''
							);

							if ( $structure->parts[ $i ]->ifdparameters ) {
								// true if the dparameters array exists
								foreach ( $structure->parts[ $i ]->dparameters as $object ) {
									if ( strtolower( $object->attribute ) == 'filename' ) {
										// Get filename from email
										$attachments[ $attachmentCount ]['is_attachment'] = TRUE;
										$attachments[ $attachmentCount ]['filename'] = $object->value;
									}
								}
							}

							if ( $structure->parts[ $i ]->ifparameters ) {
								// true if the parameters array exists
								foreach ( $structure->parts[ $i ]->parameters as $object ) {
									if ( strtolower( $object->attribute ) == 'name' ) {
										// Get name from email
										$attachments[ $attachmentCount ]['is_attachment'] = TRUE;
										$attachments[ $attachmentCount ]['name'] = $object->value;
									}
								}
							}

							if ( $attachments[ $attachmentCount ]['is_attachment'] ) {
								if ( $this->mailBox[ $index ]["body"] == NULL ) {
									// Get email body if not already downloaded
									$this->mailBox[ $index ]["body"] = imap_fetchbody( $this->connection, $id, $i + 1 );
								}

								$message = $this->mailBox[ $index ]["body"]; // Assign body to current message
								$attachments[ $attachmentCount ]['encoding'] = $structure->parts[ $i ]->encoding; // Assign encoding type to current attachment.

								// Encoding Type
								switch ( $structure->parts[ $i ]->encoding ) {
									case ENC7BIT:
										$attachments[ $attachmentCount ]['attachment'] = $message; // No decoding needed
										break;
									case ENC8BIT:
										$attachments[ $attachmentCount ]['attachment'] = imap_8bit( $message ); //  Decode
										break;
									case ENCBINARY:
										$attachments[ $attachmentCount ]['attachment'] = imap_binary( $message ); //  Decode
										break;
									case ENCBASE64:
										$attachments[ $attachmentCount ]['attachment'] = base64_decode( $message ); //  Decode
										break;
									case ENCQUOTEDPRINTABLE:
										$attachments[ $attachmentCount ]['attachment'] = quoted_printable_decode( $message ); //  Decode
										break;
									case ENCOTHER:
										// Not currently handled
										break;
								}
							}
						}
					}
				}
			}

			return $attachments; // Return attachments
		}

		/**
		 * Returns the currently selected mailbox
		 *
		 * @return mixed
		 */
		public function getCurrentMailboxName() {
			return $this->currentMailbox;
		}

		/**
		 * return specified email index
		 *
		 * @param int $index
		 * @return array
		 */
		public function getMail( int $index ): array {

			$emailCount = count( $this->mailBox ); // Get the amount of messages in the mailbox

			if ( !empty( $this->mailBox[ $index ] ) ) {
				return $this->mailBox[ $index ];
			}

			return array(); // No email in the inbox
		}

		/**
		 * Get contents of mail
		 *
		 * @param      $index
		 * @param bool $peek
		 * @return bool
		 */
		public function getMailBody( $index, bool $peek = FALSE ): bool {

			if ( !empty( $this->mailBox[ $index ] ) ) {
				if ( $peek != FALSE ) {
					$peek = FT_PEEK;
				}

				$this->mailBox[ $index ]["body"] = imap_body( $this->connection, $this->mailBox[ $index ]["index"], $peek );
				return TRUE;
			}
			return FALSE;
		}

		/**
		 * Read the contents of the current mailbox
		 *
		 * @param int  $page    Page number you wish to return.
		 * @param int  $perPage Number of email you wish to return at anyone time
		 * @param bool $getBody if you wish to get the email body as well
		 * @param bool $peek    if you wish to peek at the email and not mark them as read (Default: FALSE)
		 *
		 * @return bool
		 */
		public function getMailbox( int $page = 1, int $perPage = 100, bool $getBody = FALSE, bool $peek = FALSE ): bool {

			$in = array();

			$this->countMail(); // Get the current amount of messages in the inbox

			if ( $this->mailCount > $perPage ) {
				$offset = ( ( $page - 1 ) * $perPage ); // calculate start position
				if ( $offset < 1 ) {
					$offset = 1;
				}
				if ( $offset > $this->mailCount ) {
					return FALSE;
				} // No More emails

				$end = ( $page * $perPage ); // Calculate end
				if ( $end > $this->mailCount ) {
					$end = $this->mailCount; // Make sure we don't exceed the amount of emails
				}
			} else {
				$offset = 1;
				$end = $this->mailCount; // Calculate end
			}

			for ( $idx = $offset; $idx <= $end; $idx++ ) {

				$body = NULL;

				if ( $getBody ) {
					if ( $peek != FALSE ) {
						$peek = FT_PEEK;
					}
					$body = imap_body( $this->connection, $idx, $peek );
				}

				$in[ $idx ] = array(
					'index' => $idx,
					'header' => imap_headerinfo( $this->connection, $idx ),
					'body' => $body,
					'structure' => imap_fetchstructure( $this->connection, $idx )
				);
			}

			$this->mailBox = $in; // assign messages to mailbox

			return TRUE;
		}

		public function getUnreadMessages( string $from = "" ): array {

			$unseenEmails = array();
			$unseenSearch = "UNSEEN" . ( $from == "" ? "" : ( " FROM " . $from ) );
			$unseenEmailList = $this->searchMail( $unseenSearch );

			if ( !empty( $unseenEmailList ) ) {
				foreach ( $unseenEmailList as $emailIndexNumber ) {
					$unseenEmails[ $emailIndexNumber ] = array(
						'index' => $emailIndexNumber,
						'header' => imap_headerinfo( $this->connection, $emailIndexNumber ),
						'body' => NULL,
						'structure' => imap_fetchstructure( $this->connection, $emailIndexNumber )
					);
				}
			}

			$this->mailBox = $unseenEmails; // assign messages to mailbox

			return $unseenEmails;
		}

		/**
		 * List mailboxes
		 *
		 * @return array|false
		 */
		public function listMailboxes() {

			try {
				if ( $this->connection == FALSE ) {
					$this->boxes = NULL; // No connection to server.  return NULL
				} else {
					$this->boxes = imap_list( $this->connection, $this->server, '*' );
				}
			} catch ( Exception $e ) {
			}

			return $this->boxes;
		}

		/**
		 * Move email message to folder
		 *
		 * @param int    $index  ID of email to move
		 * @param string $folder Path where to move mail to
		 * @return bool
		 */
		public function moveMail( int $index, string $folder = 'INBOX.Processed' ): bool {

			$moveReturn = imap_mail_move( $this->connection, $index, $folder ); // Move the email
			imap_expunge( $this->connection ); // Delete all messages marked for deletion

			$this->getMailbox(); // Update the Inbox

			return $moveReturn;
		}

		/**
		 * Search mailbox for given message
		 *
		 * @param String $search          Search Parameter + Search String
		 *                                ALL, ANSWERED, BCC, BEFORE(Date), BODY,
		 *                                CC, DELETED, FLAGGED, FROM, KEYWORD,
		 *                                NEW, OLD, ON, RECENT, SEEN, SINCE(date),
		 *                                SUBJECT, TEXT, TO, UNANSWERED, UNDELETED,
		 *                                UNFLAGGED, UNKEYWORD, UNSEEN
		 * @return array|false
		 */
		public function searchMail( string $search ) {
			return imap_search( $this->connection, $search );
		}

		/**
		 * makes the given mailbox the currently active mailbox.
		 *
		 * @param String $mailboxName Name of Mailbox to select
		 * @return bool
		 */
		public function selectMailbox( string $mailboxName ): bool {

			$mailboxFound = FALSE;
			$mailboxes = $this->listMailboxes();

			if ( !empty( $mailboxes ) ) {
				// Loop through Mailboxes
				foreach ( $mailboxes as $midx => $value ) {
					if ( stristr( $value, $mailboxName ) ) {
						// Mailbox found in list
						$mailboxFound = $this->changeMailbox( $midx );

						break;
					}
				}
			}

			return $mailboxFound;
		}

		public function sendMail( $mailTo, $mailSubject, $mailMessage, $mailCC = "", $mailBCC = "" ): bool {
			// TODO Write Send Mail Routine
			return imap_mail( $mailTo, $mailSubject, $mailMessage, "", $mailCC, $mailBCC, "" );
		}
	}

?>