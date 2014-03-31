<?php

require_once(dirname(__FILE__)."/../bin/config.php");
require_once(dirname(__FILE__)."/Net_SmartIRC/Net/SmartIRC.php");
require_once(WWW_DIR."/lib/framework/db.php");
require_once(WWW_DIR."/lib/groups.php");
require_once("functions.php");


/**
 * Class IRCScraperRun
 */
class IRCScraper
{
	/**
	 * Array of current pre info.
	 * @var array
	 */
	protected $CurPre;

	/**
	 * Array of old pre info.
	 * @var array
	 */
	protected $OldPre;

	/**
	 * List of groups and their ID's
	 * @var array
	 */
	protected $groupList;

	/**
	 * @var Net_SmartIRC
	 */
	protected $IRC = null;

	/**
	 * Current server.
	 * efnet | corrupt | zenet
	 * @var string
	 */
	protected $serverType;

	/**
	 * Run this in silent mode (no text output).
	 * @var bool
	 */
	protected $silent;

	/**
	 * Construct
	 *
	 * @param Net_SmartIRC $irc          Instance of class Net_SmartIRC
	 * @param string       $serverType   efnet | corrupt | zenet
	 * @param bool         $silent       Run this in silent mode (no text output).
	 * @param bool         $debug        Turn on Net_SmartIRC debug?
	 * @param bool         $socket       Use real sockets or fsock?
	 */
	public function __construct(&$irc, $serverType, &$silent = false, &$debug = false, &$socket = true)
	{
		$this->db = new DB();
		$this->groups = new Groups();
        $this->functions = new Functions();
		$this->groupList = array();
		$this->IRC = $irc;
		if ($debug) {
			$this->IRC->setDebug(SMARTIRC_DEBUG_ALL);
		}
		$this->serverType = $serverType;
		$this->silent = $silent;
		$this->resetPreVariables();
		$this->startScraping($socket);
	}

	/**
	 * Destruct
	 */
	public function __destruct()
	{
		// Disconnect from IRC cleanly.
		if (!is_null($this->IRC)) {
			if (!$this->silent) {
				echo
					'Disconnecting from ' .
					$this->serverType .
					'.' .
					PHP_EOL;
			}
			$this->IRC->disconnect();
		}
	}

	/**
	 * Main method for scraping.
	 *
	 * @param bool $socket  Use real sockets or fsock?
	 */
	protected function startScraping(&$socket)
	{
		switch($this->serverType) {
			case 'efnet':
				$server   = SCRAPE_IRC_EFNET_SERVER;
				$port     = SCRAPE_IRC_EFNET_PORT;
				$nickname = SCRAPE_IRC_EFNET_NICKNAME;
				$username = SCRAPE_IRC_EFNET_USERNAME;
				$realname = SCRAPE_IRC_EFNET_REALNAME;
				$password = SCRAPE_IRC_EFNET_PASSWORD;
				$channelList = array(
					// Channel                             Password.
					'#alt.binaries.inner-sanctum'          => null,
					'#alt.binaries.cd.image'               => null,
					'#alt.binaries.movies.divx'            => null,
					'#alt.binaries.sounds.mp3.complete_cd' => null,
					'#alt.binaries.warez'                  => null,
					'#alt.binaries.teevee'                 => 'teevee',
					'#alt.binaries.moovee'                 => 'moovee',
					'#alt.binaries.erotica'                => 'erotica',
					'#alt.binaries.flac'                   => 'flac',
					'#alt.binaries.foreign'                => 'foreign',
					'#alt.binaries.console.ps3'            => null
				);
				// Check if the user is ignoring channels.
				if (defined('SCRAPE_IRC_EFNET_IGNORED_CHANNELS') && SCRAPE_IRC_EFNET_IGNORED_CHANNELS != '') {
					$ignored = explode(',', SCRAPE_IRC_EFNET_IGNORED_CHANNELS);
					$newList = array();
					foreach($channelList as $channel => $password) {
						if (!in_array($channel, $ignored)) {
							$newList[$channel] = $password;
						}
					}
					if (empty($newList)) {
						exit('ERROR: You have ignored every group there is to scrape!' . PHP_EOL);
					}
					$channelList = $newList;
					unset($newList);
				}
				$regex =
					// Simple regex, more advanced regex below when doing the real checks.
					'/' .
						'FILLED.*Pred.*ago' .                                  // a.b.inner-sanctum
						'|' .
						'Thank.*you.*Req.*Id.*Request' .                       // a.b.cd.image, a.b.movies.divx, a.b.sounds.mp3.complete_cd, a.b.warez
						'|' .
						'Thank.*?you.*?You.*?are.*?now.*?Filling.*?ReqId.*?' . // a.b.flac a.b.teevee
						'|' .
						'Thank.*?You.*?Request.*?Filled!.*?ReqId' .            // a.b.moovee a.b.foreign
						'|' .
						'That.*?was.*?awesome.*?Shall.*?ReqId' .               // a.b.erotica
						'|' .
						'person.*?filling.*?request.*?for:.*?ReqID:' .         // a.b.console.ps3
					'/i';
				// a.b.teevee:
				// Nuke    : [NUKE] ReqId:[183497] [From.Dusk.Till.Dawn.S01E01.720p.HDTV.x264-BATV] Reason:[bad.ivtc.causing.jerky.playback.due.to.dupe.and.missing.frames.in.segment.from.16m.to.30m]
				// Un nuke : [UNNUKE] ReqId:[183449] [The.Biggest.Loser.AU.S09E29.PDTV.x264-RTA] Reason:[get.samplefix]
				break;

			case 'corrupt':
				$server      = SCRAPE_IRC_CORRUPT_SERVER;
				$port        = SCRAPE_IRC_CORRUPT_PORT;
				$nickname    = SCRAPE_IRC_CORRUPT_NICKNAME;
				$username    = SCRAPE_IRC_CORRUPT_USERNAME;
				$realname    = SCRAPE_IRC_CORRUPT_REALNAME;
				$password    = SCRAPE_IRC_CORRUPT_PASSWORD;
				$channelList = array('#pre' => null);
				$regex       = '/PRE:.+?\[.+?\]/i'; // #pre
				// Nuke    : NUKE: Miclini-Sunday_Morning_P1-DIRFIX-DAB-03-30-2014-G4E [dirfix.must.state.name.of.release.being.fixed] [EthNet]
				// Un nuke : UNNUKE: Youssoupha-Sur_Les_Chemins_De_Retour-FR-CD-FLAC-2009-0MNi [flac.rule.4.12.states.ENGLISH.artist.and.title.must.be.correct.and.this.is.not.ENGLISH] [LocalNet]
				// Mod nuke: MODNUKE: Miclini-Sunday_Morning_P1-DIRFIX-DAB-03-30-2014-G4E [nfo.must.state.name.of.release.being.fixed] [EthNet]
				break;

			case 'zenet':
				$server      = SCRAPE_IRC_ZENET_SERVER;
				$port        = SCRAPE_IRC_ZENET_PORT;
				$nickname    = SCRAPE_IRC_ZENET_NICKNAME;
				$username    = SCRAPE_IRC_ZENET_USERNAME;
				$realname    = SCRAPE_IRC_ZENET_REALNAME;
				$password    = SCRAPE_IRC_ZENET_PASSWORD;
				$channelList = array('#Pre' => null);
				$regex       = '/^\(PRE\)\s+\(/'; // #Pre
				// Nuke   : (NUKE) (German_TOP100_Single_Charts_31_03_2014-MCG) (selfmade.compilations.not.allowed)
				// Un nuke: (UNNUKE) (The.Biggest.Loser.AU.S09E29.PDTV.x264-RTA) (get.samplefix)
				break;

			default:
				return;
		}

		// Use real sockets instead of fsock.
		$this->IRC->setUseSockets($socket);

		// This will scan channel messages for the regex above.
		$this->IRC->registerActionhandler(SMARTIRC_TYPE_CHANNEL, $regex, $this, 'check_type');

		// If there's a problem during connection, try to reconnect.
		$this->IRC->setAutoRetry(true);

		// If problem connecting, wait 5 seconds before reconnecting.
		$this->IRC->setReconnectdelay(5);

		// Try 4 times before giving up.
		$this->IRC->setAutoRetryMax(4);

		// If a network error happens, automatically reconnect.
		$this->IRC->setAutoReconnect(true);

		// Connect to IRC.
		$connection = $this->IRC->connect($server, $port);
		if ($connection === false) {
			exit (
				'Error connecting to (' .
				$server .
				':' .
				$port .
				'). Please verify your server information and try again.' .
				PHP_EOL
			);
		}

		// Login to IRC.
		$this->IRC->login(
			// Nick name.
			$nickname,
			// Real name.
			$realname,
			// User mode.
			0,
			// User name.
			$username,
			// Password.
			(empty($password) ? null : $password)
		);

		// Join channels.
		$this->IRC->join($channelList);

		if (!$this->silent) {
			echo
				'[' .
				date('r') .
				'] [Scraping of IRC channels for (' .
				$server .
				$port .
				') (' .
				$nickname .
				') started.]' .
				PHP_EOL;
		}

		// Wait for action handlers.
		$this->IRC->listen();

		// If we return from action handlers, disconnect from IRC.
		$this->IRC->disconnect();
	}

	/**
	 * Check channel and poster, send to right method.
	 *
	 * @param object $irc
	 * @param object $data
	 */
	public function check_type($irc, $data)
	{
		$channel = strtolower($data->channel);
		$poster  = strtolower($data->nick);

		switch ($poster) {
			case 'sanctum':
				if ($channel === '#alt.binaries.inner-sanctum') {
					$this->inner_sanctum($data->message);
				}
				break;

			case 'alt-bin':
				$this->alt_bin($data->message, $channel);
				break;

			case 'pr3':
				$this->corrupt_pre($data->message);
				break;

			case 'abflac':
				if ($channel === '#alt.binaries.flac') {
					$this->ab_flac($data->message);
				}
				break;

			case 'abking':
				if ($channel === '#alt.binaries.moovee') {
					$this->ab_moovee($data->message);
				}
				break;

			case 'ginger':
				if ($channel === '#alt.binaries.erotica') {
					$this->ab_erotica($data->message);
				}
				break;

			case 'abgod':
				if ($channel === '#alt.binaries.teevee') {
					$this->ab_teevee($data->message);
				}
				break;

			case 'theannouncer':
				if ($channel === '#pre') {
					$this->zenet_pre($data->message);
				}
				break;

			case 'abqueen':
				if ($channel === '#alt.binaries.foreign') {
					$this->ab_foreign($data->message);
				}
				break;

			case 'binarybot':
				if ($channel === '#alt.binaries.console.ps3') {
					$this->ab_console_ps3($data->message);
				}
				break;

			default:
				break;
		}
	}

	/**
	 * Get pre date from wD xH yM zS ago string.
	 *
	 * @param $agoString
	 */
	protected function getTimeFromAgo($agoString)
	{
		$predate = 0;
		// Get pre date from this format : 10m 54s
		if (preg_match('/((?P<day>\d+)d)?\s*((?P<hour>\d+)h)?\s*((?P<min>\d+)m)?\s*((?P<sec>\d+)s)?/i', $agoString, $matches)) {
			if (!empty($matches['day'])) {
				$predate += ((int)($matches['day']) * 86400);
			}
			if (!empty($matches['hour'])) {
				$predate += ((int)($matches['hour']) * 3600);
			}
			if (!empty($matches['min'])) {
				$predate += ((int)($matches['min']) * 60);
			}
			if (!empty($matches['sec'])) {
				$predate += (int)$matches['sec'];
			}
			if ($predate !== 0) {
				$predate = (time() - $predate);
			}
		}
		$this->CurPre['predate'] = ($predate === 0 ? '' : $this->functions->from_unixtime($predate));
	}

	/**
	 * Go through regex matches, find PRE info.
	 *
	 * @param array $matches
	 */
	protected function siftMatches(&$matches)
	{
		$this->CurPre['md5'] = $this->db->escapeString(md5($matches['title']));
		$this->CurPre['title'] = $matches['title'];

		if (isset($matches['reqid'])) {
			$this->CurPre['reqid'] = $matches['reqid'];
		}
		if (isset($matches['size'])) {
			$this->CurPre['size'] = $matches['size'];
		}
		if (isset($matches['predago'])) {
			$this->getTimeFromAgo($matches['predago']);
		}
		if (isset($matches['category'])) {
			$this->CurPre['category'] = $matches['category'];
		}
		$this->checkForDupe();
	}

	/**
	 * Gets new PRE from #a.b.erotica
	 *
	 * @param string $message The IRC message to parse.
	 */
	protected function ab_erotica(&$message)
	{
		//That was awesome [*Anonymous*] Shall we do it again? ReqId:[326377] [0-Day] [FULL 23x15MB Gyno-X.14.03.08.Annie.XXX.MP4-FUNKY] Filenames:[GX080314X8HRRZ2A8] Comments:[0] Watchers:[0] Total Size:[322MB] Points Earned:[23]
		//That was awesome [*Anonymous*] Shall we do it again? ReqId:[326264] [HD-Clip] [FULL 16x50MB TeenSexMovs.14.03.30.Daniela.XXX.720p.WMV-iaK] Filenames:[iak-teensexmovs-140330] Comments:[0] Watchers:[0] Total Size:[753MB] Points Earned:[54] [Pred 3m 20s ago]
		if (preg_match('/ReqId:\[(?P<reqid>\d+)\]\s+\[.+?\]\s+\[FULL\s+\d+x\d+[KMGTP]?B\s+(?P<title>.+?)\].+?Size:\[(?P<size>.+?)\](.+?\[Pred\s+(?P<predago>.+?)\s+ago\])?/i', $message, $matches)) {
			$this->CurPre['source']   = '#a.b.erotica';
			$this->CurPre['groupid']  = $this->getGroupID('alt.binaries.erotica');
			$this->CurPre['category'] = 'XXX';
			$this->siftMatches($matches);
		}
	}

	/**
	 * Gets new PRE from #a.b.flac
	 *
	 * @param string $message The IRC message to parse.
	 */
	protected function ab_flac(&$message)
	{
		//Thank You [*Anonymous*] You are now Filling ReqId:[42548] [FULL VA-Diablo_III_Reaper_of_Souls_Collectors_Edition_Soundtrack-CD-FLAC-2014-BUDDHA] [Pred 55s ago]
		if (preg_match('/You\s+are\s+now\s+Filling\s+ReqID:.*?\[(?P<reqid>\d+)\]\s+\[FULL\s+(?P<title>.+?)\]\s+\[Pred\s+(?P<predago>.+?)\s+ago\]/i', $message, $matches)) {
			$this->CurPre['source']  = '#a.b.flac';
			$this->CurPre['groupid'] = $this->getGroupID('alt.binaries.sounds.flac');
			$this->siftMatches($matches);
		}
	}

	/**
	 * Gets new PRE from #a.b.moovee
	 *
	 * @param string $message The IRC message to parse.
	 */
	protected function ab_moovee(&$message)
	{
		//Thank You [*Anonymous*] Request Filled! ReqId:[140445] [FULL 94x50MB Burning.Daylight.2010.720p.BluRay.x264-SADPANDA] Requested by:[*Anonymous* 3h 29m ago] Comments:[0] Watchers:[0] Points Earned:[314] [Pred 4h 29m ago]
		if (preg_match('/ReqId:\[(?P<reqid>\d+)\]\s+\[FULL\s+\d+x\d+[MGPTK]?B\s+(?P<title>.+?)\]\s+.+?\[Pred\s+(?P<predago>.+?)\s+ago\]/i', $message, $matches)) {
			$this->CurPre['source']   = '#a.b.moovee';
			$this->CurPre['groupid']  = $this->getGroupID('alt.binaries.moovee');
			$this->CurPre['category'] = 'Movies';
			$this->siftMatches($matches);
		}
	}

	/**
	 * Gets new PRE from #a.b.foreign
	 *
	 * @param string $message The IRC message to parse.
	 */
	protected function ab_foreign(&$message)
	{
		//Thank You [*Anonymous*] Request Filled! ReqId:[61525] [Movie] [FULL 95x50MB Wadjda.2012.PAL.MULTI.DVDR-VIAZAC] Requested by:[*Anonymous* 5m 13s ago] Comments:[0] Watchers:[0] Points Earned:[317] [Pred 8m 27s ago]
		if (preg_match('/ReqId:\[(?P<reqid>\d+)\]\s+\[(?P<category>.+?)\]\s+\[FULL\s+\d+x\d+[MGPTK]?B\s+(?P<title>.+?)\]\s+.+?\[Pred\s+(?P<predago>.+?)\s+ago\]/i', $message, $matches)) {
			$this->CurPre['source']  = '#a.b.foreign';
			$this->CurPre['groupid'] = $this->getGroupID('alt.binaries.mom');
			$this->siftMatches($matches);
		}
	}

	/**
	 * Gets new PRE from #a.b.teevee
	 *
	 * @param string $message The IRC message to parse.
	 */
	protected function ab_teevee(&$message)
	{
		//Thank You [*Anonymous*] You are now Filling ReqId:[183443] [FULL Ant.and.Decs.Saturday.Night.Takeaway.S11E06.HDTV.x264-W4F] [Pred 1m 43s ago]
		if (preg_match('/You\s+are\s+now\s+Filling\s+ReqId:\[(?P<reqid>\d+)\]\s+\[FULL\s+(?P<title>.+?)\]\s+\[Pred\s+(?P<predago>.+?)\s+ago\]/', $message, $matches)) {
			$this->CurPre['source']   = '#a.b.teevee';
			$this->CurPre['grpoupid'] = $this->getGroupID('alt.binaries.teevee');
			$this->siftMatches($matches);
		}
	}

	/**
	 * Gets new PRE from #a.b.console.ps3
	 *
	 * @param string $message The IRC message to parse.
	 */
	protected function ab_console_ps3(&$message)
	{
		//<BinaryBot> [Anonymous person filling request for: FULL 56 Ragnarok.Odyssey.ACE.PS3-iMARS NTSC BLURAY imars-ragodyace-ps3 56x100MB by Khaine13 on 2014-03-29 13:14:12][ReqID: 4888][You get a bonus of 6 for a total points earning of: 62 for filling with 10% par2s!][Your score will be adjusted once you have -filled 4888]
		if (preg_match('/\s+FULL\d+\s+(?P<title>.+?)\s+.+?\]\[ReqID:\s+(?P<reqid>\d+)\]\[/', $message, $matches)) {
			$this->CurPre['source']   = '#a.b.console.ps3';
			$this->CurPre['groupid'] = $this->getGroupID('alt.binaries.console.ps3');
			$this->CurPre['category'] = 'PS3';
			$this->siftMatches($matches);
		}
	}

	/**
	 * Gets new PRE from #Pre on zenet
	 *
	 * @param string $message The IRC message to parse.
	 */
	protected function zenet_pre(&$message)
	{
		//(PRE) (XXX) (The.Golden.Age.Of.Porn.Candy.Samples.XXX.WEBRIP.WMV-GUSH)
		if (preg_match('/^\(PRE\)\s+\((?P<category>.+?)\)\s+\((?P<title>.+?)\)$/', $message, $matches)) {
			$this->CurPre['source'] = '#Pre@zenet';
			$this->siftMatches($matches);
		}
	}

	/**
	 * Gets new PRE from #pre on Corrupt-net
	 *
	 * @param string $message The IRC message to parse.
	 */
	protected function corrupt_pre(&$message)
	{
		//PRE: [TV-X264] Tinga.Tinga.Fabeln.S02E11.Warum.Bienen.stechen.GERMAN.WS.720p.HDTV.x264-RFG
		if (preg_match('/^PRE:\s+\[(?P<category>.+?)\]\s+(?P<title>.+)$/i', $message, $matches)) {
			$this->CurPre['source'] = '#pre@corrupt';
			$this->siftMatches($matches);
		}
	}

	/**
	 * Gets new PRE from #a.b.inner-sanctum.
	 *
	 * @param string $message The IRC message to parse.
	 */
	protected function inner_sanctum(&$message)
	{	//[FILLED] [ 341953 | Emilie_Simon-Mue-CD-FR-2014-JUST | 16x79 | MP3 | *Anonymous* ] [ Pred 10m 54s ago ]
		//06[FILLED] [ 342184 06| DJ_Tuttle--Optoswitches_(FF_011)-VINYL-1997-CMC_INT 06| 7x47 06| MP3 06| *Anonymous* 06] 06[ Pred 5h 46m 10s ago 06]"
		if (preg_match('/FILLED.*?\]\s+\[\s+(?P<reqid>\d+)\s+.*?\|\s+(?P<title>.+?)\s+.*?\|\s+.+?\s+.*?\|\s+(?P<category>.+?)\s+.*?\|\s+.+?\s+.*?\]\s+.*?\[\s+Pred\s+(?P<predago>.+?)\s+ago\s+.*?\]/i', $message, $matches)) {
			$this->CurPre['source']  = '#a.b.inner-sanctum';
			$this->CurPre['groupid'] = $this->getGroupID('alt.binaries.inner-sanctum');
			$this->siftMatches($matches);
		}
	}

	/**
	 * Get new PRE from Alt-Bin groups.
	 *
	 * @param string $message The IRC message from the bot.
	 * @param string $channel The IRC channel name.
	 */
	protected function alt_bin(&$message, &$channel)
	{
		//Thank you<Bijour> Req Id<137732> Request<The_Blueprint-Phenomenology-(Retail)-2004-KzT *Pars Included*> Files<19> Dates<Req:2014-03-24 Filling:2014-03-29> Points<Filled:1393 Score:25604>
		//Thank you<gizka> Req Id<42948> Request<Bloodsport.IV.1999.FS.DVDRip.XviD.iNT-EwDp *Pars Included*> Files<55> Dates<Req:2014-03-22 Filling:2014-03-29> Points<Filled:93 Score:5607>
		if (preg_match('/Req.+?Id.*?<.*?(?P<reqid>\d+).*?>.*?Request.*?<\d{0,2}(?P<title>.+?)(\s+\*Pars\s+Included\*\d{0,2}>|\d{0,2}>)\s+/i', $message, $matches)) {
			$this->CurPre['source']  = str_replace('#alt.binaries', '#a.b', $channel);
			$this->CurPre['groupid'] = $this->getGroupID(str_replace('#', '', $channel));
			$this->siftMatches($matches);
		}
	}

	/**
	 * Check if we already have the PRE.
	 *
	 * @return bool True if we already have, false if we don't.
	 */
	protected function checkForDupe()
	{
		$this->OldPre = $this->db->queryOneRow(sprintf('SELECT ID FROM prehash WHERE md5 = %s', $this->CurPre['md5']));
		if ($this->OldPre === false) {
			$this->insertNewPre();
		} else {
			$this->updatePre();
		}
	}

	/**
	 * Insert new PRE into the DB.
	 */
	protected function insertNewPre()
	{
		if (empty($this->CurPre['title'])) {
			return;
		}

		$query = 'INSERT INTO prehash (';

		$query .= (!empty($this->CurPre['size'])     ? 'size, '      : '');
		$query .= (!empty($this->CurPre['category']) ? 'category, '  : '');
		$query .= (!empty($this->CurPre['source'])   ? 'source, '    : '');
		$query .= (!empty($this->CurPre['reqid'])    ? 'requestID, ' : '');
		$query .= (!empty($this->CurPre['groupid'])  ? 'groupID, '   : '');

		$query .= 'predate, md5, title, adddate) VALUES (';

		$query .= (!empty($this->CurPre['size'])     ? $this->db->escapeString($this->CurPre['size'])     . ', '   : '');
		$query .= (!empty($this->CurPre['category']) ? $this->db->escapeString($this->CurPre['category']) . ', '   : '');
		$query .= (!empty($this->CurPre['source'])   ? $this->db->escapeString($this->CurPre['source'])   . ', '   : '');
		$query .= (!empty($this->CurPre['reqid'])    ? $this->CurPre['reqid']                             . ', '   : '');
		$query .= (!empty($this->CurPre['groupid'])  ? $this->CurPre['groupid']                           . ', '   : '');
		$query .= (!empty($this->CurPre['predate'])  ? $this->CurPre['predate']                           . ', '   : 'NOW(), ');

		$query .= '%s, %s, NOW())';

		$this->db->exec(
			sprintf(
				$query,
				$this->CurPre['md5'],
				$this->db->escapeString($this->CurPre['title'])
			)
		);

		$this->doEcho(true);

		$this->resetPreVariables();
	}

	/**
	 * Updates PRE data in the DB.
	 */
	protected function updatePre()
	{
		if (empty($this->CurPre['title'])) {
			return;
		}

		$query = 'UPDATE prehash SET ';

		$query .= (!empty($this->CurPre['size'])     ? 'size = '      . $this->db->escapeString($this->CurPre['size'])     . ', ' : '');
		$query .= (empty($this->OldPre['category']) && !empty($this->CurPre['category']) ? 'category = '  . $this->db->escapeString($this->CurPre['category']) . ', ' : '');
		$query .= (!empty($this->CurPre['source'])   ? 'source = '    . $this->db->escapeString($this->CurPre['source'])   . ', ' : '');
		$query .= (!empty($this->CurPre['reqid'])    ? 'requestID = ' . $this->CurPre['reqid']                             . ', ' : '');
		$query .= (!empty($this->CurPre['groupid'])  ? 'groupID = '   . $this->CurPre['groupid']                           . ', ' : '');
		$query .= (!empty($this->CurPre['predate'])  ? 'predate = '   . $this->CurPre['predate']                           . ', ' : '');

		if ($query === 'UPDATE prehash SET '){
			return;
		}

		$query .= 'title = ' . $this->db->escapeString($this->CurPre['title']);
		$query .= ' WHERE md5 = ' . $this->CurPre['md5'];

		$this->db->exec($query);

		$this->doEcho(false);

		$this->resetPreVariables();
	}

	/**
	 * Echo new or update pre to CLI.
	 *
	 * @param bool $new
	 */
	protected function doEcho($new = true)
	{
		if (!$this->silent) {
			echo
				'[' .
				date('r') .
				($new ? '] [ Added Pre ] [' : '] [Updated Pre] [') .
				$this->CurPre['source'] .
				'] [' .
				$this->CurPre['title'] .
				']' .
				(!empty($this->CurPre['category']) ? ' [' . $this->CurPre['category'] . ']' : '') .
				PHP_EOL;
		}
	}

	/**
	 * Get a group ID for a group name.
	 *
	 * @param string $groupName
	 *
	 * @return mixed
	 */
	protected function getGroupID($groupName)
	{
		if (!isset($this->groupList[$groupName])) {
			$this->groupList[$groupName] = $this->functions->getIDByName($groupName);
		}
		return $this->groupList[$groupName];
	}

	/**
	 * After updating or inserting new PRE, reset these.
	 */
	protected function resetPreVariables()
	{
		$this->OldPre = array();
		$this->CurPre =
			array(
				'title'    => '',
				'md5'      => '',
				'size'     => '',
				'predate'  => '',
				'category' => '',
				'source'   => '',
				'groupid'  => '',
				'reqid'    => ''

			);
	}
}