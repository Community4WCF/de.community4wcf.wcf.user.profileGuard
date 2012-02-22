<?php
// wcf imports
require_once(WCF_DIR.'lib/system/event/EventListener.class.php');
require_once(WCF_DIR.'lib/data/mail/Mail.class.php');

/**
 * Logs changes to user profiles.
 *
 * @author	-noone-
 * @copyright	2009 inwcf.de
 * @license	Creative Commons Attribution-Share Alike 3.0 Germany <http://creativecommons.org/licenses/by-sa/3.0/de/>
 * @package	de.community4wcf.wcf.user.guard
 * @subpackage	system.event.listener
 * @category 	User Profile Change Guard
 */
class UserProfileChangeListener implements EventListener {
	/**
	 * Selected groupIDs divided by comma (x,y,z,...).
	 *
	 * @var string
	 */
	public $groupIDs = PROFILEGUARD_RECIPIENTS;

	// data
	protected $newName = false;
	protected $isQuit = false;
	protected $isCancel = false;
	protected $newAvatar = false;
	protected $allChanges = '';

	/**
	 * @see EventListener::execute()
	 */
	public function execute($eventObj, $className, $eventName) {
		// no group selected
		if (!$this->groupIDs) return;

		// escape groupIDs
		$this->groupIDs = implode(',', ArrayUtil::toIntegerArray(ArrayUtil::trim(explode(',', $this->groupIDs), true)));

		// check username and quit
		if ($className == 'AccountManagementForm') {
			if ($eventObj->username != WCF::getUser()->username) $this->newName = true;
			if ($eventObj->quit) $this->isQuit = true;
			if ($eventObj->cancelQuit) $this->isCancel = true;
		}
		// check avatar
		if ($className == 'AvatarEditForm') {
			$this->newAvatar = true;
		}

		// get changes
		$this->allChanges = $this->getChanges();

		// send e-mail
		if (PROFILEGUARD_EMAILNOTIFICATION && !empty($this->allChanges)) {
			$this->sendMail();
		}
	}

	/**
	 * Returns all requested changes.
	 *
	 * @return	string
	 */
	protected function getChanges() {
		$changes = array();
		if ($this->newName && PROFILEGUARD_NOTIFYNAME) $changes[] = WCF::getLanguage()->get('wcf.profileguard.newName');
		if ($this->isQuit && PROFILEGUARD_NOTIFYQUIT) $changes[] = WCF::getLanguage()->get('wcf.profileguard.isQuit');
		if ($this->isCancel && PROFILEGUARD_NOTIFYCANCEL) $changes[] = WCF::getLanguage()->get('wcf.profileguard.isCancel');
		if ($this->newAvatar && PROFILEGUARD_NOTIFYAVATAR) $changes[] = WCF::getLanguage()->get('wcf.profileguard.newAvatar');

		return implode(', ', $changes);
	}

	/**
	 * Sends e-mail notifications to all recipients.
	 */
	protected function sendMail() {
		// get recipients
		$sql = "SELECT		username,
					email
			FROM		wcf".WCF_N."_user
			WHERE		userID IN (
						SELECT	userID
						FROM	wcf".WCF_N."_user_to_groups
						WHERE	groupID IN (".$this->groupIDs.")
					)";
		$result = WCF::getDB()->sendQuery($sql);
		while ($row = WCF::getDB()->fetchArray($result)) {
			$recipients[$row['username']] = $row['email'];
		}

		// send e-mail
		$mail = new Mail($recipients, WCF::getLanguage()->get('wcf.profileguard.subject'), WCF::getLanguage()->get('wcf.profileguard.message.mail', array(
			'$username' => WCF::getUser()->username,
			'$changes' => $this->allChanges
		)), MAIL_ADMIN_ADDRESS);
		$mail->send();
	}
}
?>