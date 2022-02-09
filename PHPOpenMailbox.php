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

		private $inbox;        // Inbox
		private $msg_cnt;    // Mail Counter
		private $boxes;        // Mailbox list

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
		function setup( string $server, string $username, string $password, int $port, string $options = '/imap2/tls' ): void {

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
		function close(): void {

			$this->inbox = array();
			$this->msg_cnt = 0;

			imap_close( $this->connection );
		}

		/**
		 * @param string $box Name of the box you wish to use
		 */
		function changeMailbox( string $box ): bool {

			return imap_reopen( $this->connection, $this->boxes[ $box ] );
		}

		/**
		 * gets the current amount of messages in the mailbox
		 *
		 * @return false|int
		 */
		function countMail() {
			$this->msg_cnt = imap_num_msg( $this->connection );

			return $this->msg_cnt;
		}

		/**
		 * Get and decode attachments from selected email
		 *
		 * @param int $index
		 * @return array
		 */
		function getAttachments( int $index ): array {

			$attachmentCount = 0; // set initial value
			$attachments = array(); // initialise array

			$structure = $this->inbox[ $index ]["structure"]; // Get Mail Structure
			$id = $this->inbox[ $index ]["index"];

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
							if ( $this->inbox[ $index ]["body"] == NULL ) {
								// Get email body if not already downloaded
								$this->inbox[ $index ]["body"] = imap_fetchbody( $this->connection, $id, $i + 1 );
							}

							$message = $this->inbox[ $index ]["body"]; // Assign body to current message
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

			return $attachments; // Return attachments
		}

		/**
		 * return specified email index
		 *
		 * @param int $index
		 * @return array
		 */
		function getMail( int $index ): array {

			if ( count( $this->inbox ) > 0 ) {
				if ( $index > 0 && isset( $this->inbox[ $index ] ) ) {
					return $this->inbox[ $index ];
				}
			}

			return array(); // No email in the inbox
		}

		/**
		 * Get contents of mail
		 *
		 * @param      $index
		 * @param bool $peek
		 */
		function getMailBody( $index, bool $peek = FALSE ) {

			if ( $peek != FALSE ) {
				$peek = FT_PEEK;
			}

			$this->inbox[ $index ]["body"] = imap_body( $this->connection, $this->inbox[ $index ]["index"], $peek );
		}

		/**
		 * Read the contents of the current mailbox
		 *
		 * @param int  $page    Page number you wish to return.
		 * @param int  $perPage Number of email you wish to return at anyone time
		 * @param bool $getBody if you wish to get the email body as well
		 * @param bool $peek    if you wish to peek at the email and not mark them as read (Default: FALSE)
		 *
		 * @return array
		 */
		function getMailbox( int $page = 1, int $perPage = 100, bool $getBody = FALSE, bool $peek = FALSE ): array {

			$in = array();

			$this->countMail(); // Get the current amount of messages in the inbox

			if ( $this->msg_cnt > $perPage ) {
				$offset = ( ( $page - 1 ) * $perPage ); // calculate start position
				if ( $offset < 1 ) {
					$offset = 1;
				}
				if ( $offset > $this->msg_cnt ) {
					return $in;
				} // No More emails

				$end = ( $page * $perPage ); // Calculate end
				if ( $end > $this->msg_cnt ) {
					$end = $this->msg_cnt; // Make sure we don't exceed the amount of emails
				}
			} else {
				$offset = 1;
				$end = $this->msg_cnt; // Calculate end
			}

			for ( $idx = $offset; $idx < $end; $idx++ ) {

				$body = NULL;

				if ( $getBody ) {
					if ( $peek != FALSE ) {
						$peek = FT_PEEK;
					}
					$body = imap_body( $this->connection, $idx, $peek );
				}

				$in[] = array(
					'index' => $idx,
					'header' => imap_headerinfo( $this->connection, $idx ),
					'body' => $body,
					'structure' => imap_fetchstructure( $this->connection, $idx )
				);
			}

			$this->inbox = $in;

			return $in;
		}

		/**
		 * List mailboxes
		 *
		 * @return array|false
		 */
		function listMailboxes() {

			$this->boxes = imap_list( $this->connection, $this->server, '*' );

			return $this->boxes;
		}

		/**
		 * Move email message to folder
		 *
		 * @param int    $index  ID of email to move
		 * @param string $folder Path where to move mail to
		 * @return bool
		 */
		function moveMail( int $index, string $folder = 'INBOX.Processed' ): bool {

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
		function searchMail( string $search ) {

			return imap_search( $this->connection, $search );
		}

		function sendMail( $mailTo, $mailSubject, $mailMessage, $mailCC = "", $mailBCC = "" ): bool {
			// TODO Write Send Mail Routine
			return imap_mail( $mailTo, $mailSubject, $mailMessage, "", $mailCC, $mailBCC, "" );
		}
	}

?>