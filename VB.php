<?php

class WPVB
{

	public $current_user;
	public $vb_user;

	public $vb_root_path = '/forum/'; //staging
	#public $vb_root_path = '/../../vb/upload/'; // my local


	private $vbulletin = null;
	private $db = null;

	public function __construct(){
		require_once(ABSPATH . 'wp-includes/pluggable.php');
		global $current_user, $user_email;
		get_currentuserinfo();
		$this->current_user = $current_user;


		$isForumFound = $this->init();
		if(!$isForumFound){
			return false;
		}
		global $vbulletin, $db;

		$this->vbulletin = $vbulletin;
		$this->db = $db;



		$this->vb_user = fetch_userinfo($this->current_user->ID);
	}

	public function deleteThread($thread_id){
		$this->require_file('class_dm.php', 'includes/');
		$this->require_file('functions.php', 'includes/');
		$this->require_file('class_dm_threadpost.php', 'includes/');
		$this->require_file('functions_databuild.php', 'includes/');

		$threadinfo = fetch_threadinfo($thread_id);
		$forum_id = $threadinfo['forumid'];


		$foruminfo = fetch_foruminfo($forum_id, false);


		$postinfo = fetch_postinfo($threadinfo['firstpostid']);


		$post_userid = (isset($data['post_userid']))? $data['post_userid']:$this->current_user->ID;

		$this->vb_user = fetch_userinfo($post_userid);





		if ($threadinfo['firstpostid'] == $postinfo['postid'])
		{
			$threadman =& datamanager_init('Thread', $this->vbulletin, ERRTYPE_STANDARD, 'threadpost');
			$threadman->set_existing($threadinfo);
			$threadman->delete($foruminfo['countposts'], true, array('userid' => $this->vbulletin->userinfo['userid'], 'username' => $this->vbulletin->userinfo['username'], 'reason' => "Wp post deleted", 'keepattachments' => false));
			unset($threadman);

			if ($foruminfo['lastthreadid'] != $threadinfo['threadid'])
			{
				// just decrement the reply and thread counter for the forum
				$forumdm =& datamanager_init('Forum', $this->vbulletin, ERRTYPE_SILENT);
				$forumdm->set_existing($foruminfo);
				$forumdm->set('threadcount', 'threadcount - 1', false);
				$forumdm->set('replycount', 'replycount - 1', false);
				$forumdm->save();
				unset($forumdm);
			}
			else
			{
				// this thread is the one being displayed as the thread with the last post...
				// so get a new thread to display.
				build_forum_counters($threadinfo['forumid']);
			}
		}else
		{
			$postman =& datamanager_init('Post', $this->vbulletin, ERRTYPE_SILENT, 'threadpost');
			$postman->set_existing($postinfo);
			$postman->delete($foruminfo['countposts'], $threadinfo['threadid'], true, array('userid' => $this->vbulletin->userinfo['userid'], 'username' => $this->vbulletin->userinfo['username'], 'reason' => "Wp post deleted", 'keepattachments' => false));
			unset($postman);

			if ($node = get_nodeFromThreadid($threadinfo['threadid']))
			{
				// Expire any CMS comments cache entries.
				$expire_cache = array('cms_comments_change');
				$expire_cache[] = 'cms_comments_add_' . $node;
				$expire_cache[] = 'cms_comments_change_' . $threadinfo['threadid'];

				vB_Cache::instance()->eventPurge($expire_cache);
				vB_Cache::instance()->cleanNow();
			}

			build_thread_counters($threadinfo['threadid']);

			if ($foruminfo['lastthreadid'] != $threadinfo['threadid'])
			{
				// just decrement the reply counter
				$forumdm =& datamanager_init('Forum', $this->vbulletin, ERRTYPE_SILENT);
				$forumdm->set_existing($foruminfo);
				$forumdm->set('replycount', 'replycount - 1', false);
				$forumdm->save();
				unset($forumdm);
			}
			else
			{
				// this thread is the one being displayed as the thread with the last post...
				// need to get the lastpost datestamp and lastposter name from the thread.
				build_forum_counters($threadinfo['forumid']);
			}
		}
	}

	public function newThread(array $data){
		$this->require_file('class_dm.php', 'includes/');
		$this->require_file('class_dm_threadpost.php', 'includes/');
		$this->require_file('functions_databuild.php', 'includes/');

		$forum_id = $data['forum_id'];
		if(empty($forum_id)){
			$forum_id = $this->isForumExist($data['wp_category']);
		}

		$thread_id = $data['thread_id'];

		if(empty($thread_id)){
			$thread_id = $this->isThreadExist($forum_id, $data['wp_title']);
		}


		//create new thread
		if(!$thread_id){
			$threaddm = new vB_DataManager_Thread_FirstPost($this->vbulletin);

			$allow_smilie = '1';
			$visible = '1';

			$post_userid = (isset($data['post_userid']))? $data['post_userid']:$this->current_user->ID;

			$this->vb_user = fetch_userinfo($post_userid);


			$threaddm->do_set('forumid', $forum_id);
			$threaddm->do_set('postuserid', $post_userid);
			$threaddm->do_set('userid', $post_userid);
			$threaddm->do_set('username', $this->vb_user['username']);
			$threaddm->do_set('pagetext', $data['wp_post_text']);
			$threaddm->do_set('title', $data['wp_title']);
			$threaddm->do_set('allowsmilie', $allow_smilie);
			$threaddm->do_set('visible', $visible);

			$vb_thread_id = $threaddm->save();
			build_forum_counters($forum_id);
			return $vb_thread_id;

		}else{ //update/reply thread


			//$threadlater = new vB_DataManager_Post($this->vbulletin);

			$threadlater =& datamanager_init('Post', $this->vbulletin, ERRTYPE_ARRAY, 'threadpost');


			$foruminfo = fetch_foruminfo($forum_id, false);

			$threadinfo = fetch_threadinfo($thread_id);

			$postinfo = fetch_postinfo($threadinfo['firstpostid']);
			$this->vb_user = fetch_userinfo($postinfo['userid']);

			//echo '<pre>'; print_r($postinfo);die;

			$threadlater->set_existing($threadinfo);

			$threadlater->set_info('forum', $foruminfo);
			$threadlater->set_info('thread', $threadinfo);
			$threadlater->set_info('user', $this->vb_user);

			$threadlater->setr('userid', $postinfo['userid']);
			$threadlater->set('threadid', $thread_id);
			$threadlater->set('allowsmilie', $postinfo['allowsmilie']);
			$postusername = $postinfo['username'];
			//$threadlater->setr('username', $postusername);

			$threadlater->setr('pagetext', $data['wp_post_text'], true);
			if(isset($data['wp_title']))$threadlater->setr('title', $data['wp_title'], true);
			//$threadlater->verify_pagetext()

			$threadlater->pre_save();
			if ($threadlater->errors)
			{
				//print_r($threadlater->errors);die;
				return false;
			}
			$threadlater->save();
			//build_forum_counters($forum_id);
			return $thread_id;

		}


		return false;
	}


	public function updatePost(array $data){
		$this->require_file('class_dm.php', 'includes/');
		$this->require_file('functions.php', 'includes/');
		$this->require_file('class_dm_threadpost.php', 'includes/');
		$this->require_file('functions_databuild.php', 'includes/');

		$forum_id = $data['forum_id'];
		if(empty($forum_id)){
			$forum_id = $this->isForumExist($data['wp_category']);
		}

		$thread_id = $data['thread_id'];

		if(empty($thread_id)){
			$thread_id = $this->isThreadExist($forum_id, $data['wp_title']);
		}

		$foruminfo = fetch_foruminfo($forum_id, false);

		$dataman =& datamanager_init('Post', $this->vbulletin, ERRTYPE_ARRAY, 'threadpost');




		$threadinfo = fetch_threadinfo($thread_id);

		$postinfo = fetch_postinfo($threadinfo['firstpostid']);
		$this->vb_user = null;
		$this->vb_user = fetch_userinfo($postinfo['userid']);
		$edit = $postinfo;
		//print_r($threadinfo); die;

		// set info
		//$dataman->set_info('parseurl', (($vbulletin->options['allowedbbcodes'] & ALLOW_BBCODE_URL) AND $foruminfo['allowbbcode'] AND $data['parseurl']));
		//$dataman->set_info('posthash', $posthash);
		$dataman->set_info('forum', $foruminfo);
		$dataman->set_info('thread', $threadinfo);
		$dataman->set_info('show_title_error', true);
		$dataman->set_info('podcasturl', $edit['podcasturl']);
		$dataman->set_info('podcastsize', $edit['podcastsize']);
		$dataman->set_info('podcastexplicit', $edit['podcastexplicit']);
		$dataman->set_info('podcastkeywords', $edit['podcastkeywords']);
		$dataman->set_info('podcastsubtitle', $edit['podcastsubtitle']);
		$dataman->set_info('podcastauthor', $edit['podcastauthor']);
		$dataman->set_info('user', $this->vb_user);

		// set options
		$dataman->setr('showsignature', $edit['signature']);
		$dataman->setr('allowsmilie', $edit['enablesmilies']);
		if ($foruminfo['allowhtml'] AND $edit['htmlstate'])
		{

			$dataman->setr('htmlstate', $edit['htmlstate']);
		}

		// set data
	    $dataman->setr('userid', $this->vb_user['userid']);
		/*if ($vbulletin->userinfo['userid'] == 0)
		{
			$dataman->setr('username', $post['username']);
		}*/

		$dataman->set('threadid', $thread_id);
		$dataman->set('allowsmilie', 1);
		if(isset($data['wp_title']))$dataman->setr('title', $data['wp_title']);
		$dataman->setr('pagetext', $data['wp_post_text']);


		$postusername = $this->vb_user['username'];
		$dataman->setr('username', $postusername);

		$dataman->pre_save();
		if ($dataman->errors)
		{
			echo '<pre>';print_r($dataman->errors);die;
			return false;
		}

		if($dataman->save()){
			return $thread_id;
		}


		return false;



	}

	public function isForumExist($category, $forum_id = false){
		$sql = "SELECT forumid FROM " . $this->vbulletin->db->database . ".forum WHERE title = '$category'";
		if($forum_id){
			//$sql .= "`parentlist` LIKE '%{$forum_id},%'";
		}

		$res = $this->vbulletin->db->query_read($sql);
		$forum = $this->db->fetch_array($res);
		if(!empty($forum['forumid'])){
			return $forum['forumid'];
		}
		return false;
	}

	public function isThreadExist($forum_id, $title){
		$sql = "SELECT threadid FROM " . $this->vbulletin->db->database . ".thread WHERE forumid = '$forum_id' AND title = '$title';";
		$res = $this->vbulletin->db->query_read($sql);
		$thread = $this->db->fetch_array($res);
		if(!empty($thread['threadid'])){
			return $thread['threadid'];
		}
		return false;
	}


	public function getThread($thread_id)
	{
		//query threads
		$sql = "
		SELECT *
        FROM  " . TABLE_PREFIX . "thread
        WHERE threadid='$thread_id' AND visible = '1' AND open='1'

        ";

		$query = $this->vbulletin->db->query_read($sql);
		return $this->db->fetch_array($query);
	}

	/**
	 * Get all threads or get all threads of a specific forum
	 *
	 * @param null $forum_id
	 * @return array
	 */
	public function getThreads($forum_id = null)
	{
		//query threads
		$sql = "
		SELECT thread.threadid, thread.title, thread.dateline, thread.lastpost, thread.lastposter, thread.lastposterid, thread.visible, thread.open, thread.postusername, thread.postuserid, thread.replycount, thread.views, forum.forumid, forum.title as forumtitle
        FROM  " . TABLE_PREFIX . "thread AS thread
        LEFT JOIN  " . TABLE_PREFIX . "forum AS forum ON ( forum.forumid = thread.forumid )
        WHERE NOT ISNULL(threadid) AND visible = '1' AND open='1'

        ";

		if($forum_id){
			$sql .= " AND forum.forumid='$forum_id'";
		}

		$sql .= "ORDER BY lastpost DESC";

		$recent_threads = $this->vbulletin->db->query_read($sql);

		$threads = array();
		while($recent_thread = $this->db->fetch_array($recent_threads)){
			$threads[] = $recent_thread;
		}


		$this->db->free_result($recent_threads);


		return $threads;
	}

	/**
	 * Get threads of a specific forum
	 *
	 * @param $forum_id
	 * @return array
	 */
	public function getThreadsByForum($forum_id){
		return $this->getThreads($forum_id);
	}


	/**
	 * Get forums
	 *
	 * @return array
	 */
	public function getForums(){
		//query forums
		$sql = "
		SELECT forum.forumid, forum.title as forumtitle
        FROM  " . TABLE_PREFIX . "forum AS forum
        WHERE NOT ISNULL(forumid) AND forum.parentid!='-1'
        ";


		$sql .= "ORDER BY forumtitle ASC";

		$forumsQ = $this->vbulletin->db->query_read($sql);

		$forums = array();
		while($forum = $this->db->fetch_array($forumsQ)){
			$forums[] = $forum;
		}

		$this->db->free_result($forumsQ);

		return $forums;
	}

	/**
	 * Init the VB
	 * @return bool
	 */
	public function init()
	{
		$forum_root = $_SERVER['DOCUMENT_ROOT'] . $this->vb_root_path;
		return $this->include_global($forum_root);
	}

	/**
	 * Include vb global.php
	 *
	 * @param $forum_root
	 * @return bool
	 */
	private function include_global($forum_root)
	{
		/**
		 * Suppress file not found exception
		 *
		 * @Todo Will need to use Exception Handler instead of returning false
		 */
		if(!file_exists($forum_root . 'global.php')){
			return false;
		}
		$curdir = getcwd();
		if(file_exists($forum_root))
		chdir($forum_root);
		$this->require_file('global.php');
		chdir($curdir);
		return true;
	}


	private function require_file($filename, $path = ''){
		//echo $_SERVER['DOCUMENT_ROOT'] .$this->vb_root_path .$path. $filename.'<br>';
		if(file_exists($_SERVER['DOCUMENT_ROOT'] .$this->vb_root_path .$path. $filename)){
			require_once $_SERVER['DOCUMENT_ROOT'] .$this->vb_root_path .$path. $filename;
		}
	}

	public function getRootVBPath(){
		return $this->vb_root_path;
	}

	public function setRootVBPath($path){
		$this->vb_root_path = $path;
	}

	/**
	 * Test tool to set forum path
	 *
	 */
	private function check_roots()
	{
		$possible_root = $_SERVER['DOCUMENT_ROOT'] . 'forum/';
		$possible_root1 = $_SERVER['DOCUMENT_ROOT'] . 'forums/';

		//check for possible forum roots
		if (is_dir($possible_root) && file_exists($possible_root . 'global.php')) {
			$this->vb_root_path = $possible_root;
		} else if (is_dir($possible_root1) && file_exists($possible_root1 . 'global.php')) {
			$this->vb_root_path = $possible_root1;
		}

	}

}