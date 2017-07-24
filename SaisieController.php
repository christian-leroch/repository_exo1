<?php
mofif pour git

class Projet_SaisieController extends Zend_Controller_Action
{
	private $_affectations;
	private $_months_list = array(1 => 'Janvier',
						 2 => 'Fevrier', 
						 3 => 'Mars', 
						 4 => 'Avril', 
						 5 => 'Mai', 
						 6 => 'Juin', 
						 7 => 'Juillet', 
						 8 => 'Aout', 
						 9 => 'Septembre', 
						 10 => 'Octobre', 
						 11 => 'Novembre', 
						 12 => 'Decembre'); // pour ne pas utiliser les globals...
	private $_days_list = array(
		'Monday' => 'Lundi',
		'Tuesday' => 'Mardi',
		'Wednesday' => 'Mercredi',
		'Thursday' => 'Jeudi',
		'Friday' => 'Vendredi',
		'Saturday' => 'Samedi',
		'Sunday' => 'Dimanche'
	);
	
	public static function isWeekend($date) {
	    return (date('N', strtotime($date)) >= 6);
	}

	function init()
	{
		global $__section;
		
		if ($_GET['section'] != 'groupe')
			$this->_redirect('/groupe/Projet/Saisie'); 

	    $this->user = Activis::registry('user');

		$this->_helper->layout->assign("title", "Gestion de projets");
		$this->_helper->layout->assign("menuitem", 2);
		$this->affectations = Activis::getModel("Projet.Projet_Affectations");
		$this->productions  = Activis::getModel("Projet.Projet_Productions");
		
		$this->_flashMessenger = $this->_helper->getHelper('FlashMessenger');
		$this->_helper->layout->setLayout('menu');
		
		$this->menu = new Managing_Menu('onglet');
		// $this->menu->addItem('projets.liste', '<img src="/public/style/image/icons/table_multiple.png">Liste des projets', '/Projet/List');
		$this->menu->addItem('saisie', '<img src="/public/style/image/icons/clock_add.png">Saisie quotidienne', '/Projet/Saisie');
		//$this->menu->addItem('saisie.gestion', '<img src="/public/style/image/icons/clock_link.png">Saisie avanc&eacute;e', '/Projet/Saisie/gestion');
		$this->menu->addItem('saisie.old', '<img src="/public/style/image/icons/clock_link.png">Saisie (old)', '/Projet/Saisie/saisieOld');
		//Récupération de la team de l'utilisateur actuel pour affichage par défaut
		$user_id = $this->user->contact_id;
		$db = new PDO('mysql:dbname='.DB_PREFIX.'common;host:localhost', DB_USER_NAME, DB_PASSWORD);
		$query_fetch_user_group = 'SELECT right_group_id FROM rights_groups_users WHERE user_id = :userId';
		$binds = array(
			':userId' => $user_id
		);
		$fetch_user_group = $db->prepare($query_fetch_user_group);
		$fetch_user_group->execute($binds);

		$groupId = 0;
		while($row_user_group = $fetch_user_group->fetch(PDO::FETCH_ASSOC)){
			$groupId = (int)$row_user_group['right_group_id'];
		}

		$authorizedUsers = array(1, 2, 3, 4);
		if(in_array($groupId, $authorizedUsers)){
			$this->menu->addItem('saisie.planning_ressource', 'Planning ressource', '/Projet/Saisie/planning_ressource');
		}
		// $this->menu->addItem('planning', '<img src="/public/style/image/icons/clock.png">Planning ressource', '/Synthese/Charge');
		// $this->menu->addItem('export', '<img src="/public/style/image/icons/page_white_excel.png">Export production', '/Projet/Export/Production');
		// $this->menu->addItem('prestations', '<img src="/public/style/image/icons/page_white_excel.png">Prestations', '/Projet/Prestations');
        // $this->menu->addItem('surveillance', '<img src="/public/style/image/icons/table_multiple.png">Surveillance', '/Projet/Surveillance');
 
		$this->_helper->layout->assign('sub_menu',$this->menu->get('saisie')); // default selection
		$this->view->css = $this->view->headLink()->appendStylesheet('/public/style/saisiebeta.css');

	    /**
	     * Récupération du profil du salarié
	     *
	     * Les salariés ayant le profil ID 10 & 11 ne seront pas obligé de saisir de commentaires
	     * pour de la saisie dans "gestion", "événementiel" & "commercial"
	     */
	    $profil = 0;
	    $sql = <<<SQL
	    	SELECT
	    		s.profil
	    	FROM
	    		am_crm_salaries s
	    	WHERE
	    		s.contact_id = "{$this->user->contact_id}"
SQL;
	    $res = mysql_query($sql);
	    if (mysql_num_rows($res) > 0) {
	    	$row = mysql_fetch_assoc($res);
	    	$profil = $row['profil'];
	    }
	    $this->view->profil = $profil;

	    Activis::register('profil', $profil);
	}
	
	function gestionAction()
	{

	}
	
	function indexAction()
	{
		/** @var array $authorizedUsers Tableau des utilisateurs ayant droit à la saisie avancée*/
		$authorizedUsers = array(1, 2, 3, 4);

		//Récupération des informations du planning de la personne
		$information = $this->getPlanningInformation();
		$this->_helper->layout->assign('sub_menu',$this->menu->get('saisie')); // default selection

		/**
		 * Récupération des droits de la personne actuellement connectée pour affichage ou non de la saisie avancée
		 */
		$db = new PDO('mysql:dbname='.DB_PREFIX.'common;host:localhost', DB_USER_NAME, DB_PASSWORD);
		$query_fetch_user_group = 'SELECT right_group_id FROM rights_groups_users WHERE user_id = :userId';
		$binds = array(
			':userId' => $this->user->contact_id
		);
		$fetch_user_group = $db->prepare($query_fetch_user_group);
		$fetch_user_group->execute($binds);

		$groupId = 0;
		while($row_user_group = $fetch_user_group->fetch(PDO::FETCH_ASSOC)){
			$groupId = (int)$row_user_group['right_group_id'];
		}

		/**
		 * Vérification du droit de visu de la saisie avancée
		 */
		$isAuth = false;
		if(in_array($groupId, $authorizedUsers)){
			$isAuth = true;

			$users = Activis::getModel("Resource.Users")->fetchAll();

			$usersArray = array();
			//Si a le droit de voir tous les utilisateurs
			if($groupId == 1 || $groupId == 2 || $groupId == 4){
				$usersArray = $users;
			}
			//Si chef de section, on ne valide que les membres de son équipe
			else{
				//On récupère l'ID de la team du TL
				$query_fetch_user_team = 'SELECT equipe FROM am_crm_salaries WHERE contact_id = :userId;';
				$binds = array(':userId' => (int)$this->user->contact_id);
				$fetch_user_team = $db->prepare($query_fetch_user_team);
				$fetch_user_team->execute($binds);

				$teamId = 0;
				while($row_user_team = $fetch_user_team->fetch(PDO::FETCH_ASSOC)){
					$teamId = (int)$row_user_team['equipe'];
				}

				foreach($users as $currentUser){
					$query_fetch_user_team = 'SELECT equipe FROM am_crm_salaries WHERE contact_id = :userId;';
					$binds = array(':userId' => $currentUser->contact_id);
					$fetch_user_team = $db->prepare($query_fetch_user_team);
					$fetch_user_team->execute($binds);

					while($row_user_team = $fetch_user_team->fetch(PDO::FETCH_ASSOC)){
						if($teamId == (int)$row_user_team['equipe']){
							$usersArray[] = $currentUser;
						}
					}
				}
			}
			$this->view->assign('users', $usersArray);
		}
		$this->view->assign('isAuth', $isAuth);
		$this->view->assign('user', $this->user);

		$this->view->assign('informations', $information['informations']);
		$this->view->assign('dailyHoursArray', $information['dailyHoursArray']);
		$this->view->assign('monthsInformations', $information['monthsInformations']);
		$this->view->assign('daysAmount', $information['daysAmount']);
		$this->view->assign('dailyPlanningDetails', $information['dailyPlanningDetails']);
		$this->view->assign('productionDetails', $information['productionDetails']);
		$this->view->assign('totalProduction', $information['totalProduction']);
		$this->view->assign('plannedHours', $information['plannedHours']);
		$this->view->assign('dailyHours', $information['dailyHours']);
		$this->view->assign('markersArray', $information['markersArray']);
		$this->view->assign('daysToPrint', $information['daysToPrint']);
		$this->view->assign('absences', $information['absences']);
		$this->view->assign('userId',  $information['userId']);
		$this->view->assign('firstDayToPrint',  $information['firstDayToPrint']);
	}

	public function saisieoldAction(){
		$this->_helper->layout->assign('sub_menu',$this->menu->get('saisie.old'));

		setlocale(LC_TIME, 'english');
		$days = array();
		$total_time_day = array();
		$total_time_day[0] = 0;
		for($i = 0; $i < 8; $i++)
		{
			$time = strtotime('-'.$i.' days');
			if (date('w', $time) != 0 && date('w', $time) != 6)
			{
				$days[date('Y-m-d', $time)] = ($i > 0 ? date('d/m/Y', $time).' '.$this->_days_list[strftime('%A', $time)] : date('d/m/Y', $time).' '.'Aujourd\'hui');
				$total_time_day[date('Y-m-d', $time)] = 0;
			}
		}

		$sub_companies = explode(',', SUB_COMPANIES);
		$production = array();
		$affectations = array();

		$user_id = $this->user->contact_id;

		$this->view->user = $this->user;
		$this->view->users = Activis::getModel("Resource.Users")->fetchAll();

		$i = 0;

		foreach($sub_companies as $sub_company)
		{
			// Switch des bases de données
			mysql_select_db(DB_PREFIX.$sub_company);
			mysql_query('SET NAMES "latin-1"');

			$ets = ucfirst($sub_company);

			$projets_stmt = mysql_query('SELECT e.raison_sociale as client, e.evaluation, e.contact_id as client_id, p.nom as projet, p.offre_id, p.id as projet_id, pp.name as prestation, pp.id as prestation_id, pa.id as affectation_id,
										 IF(pp.prestation_id IN (109, 94, 124, 125, 205, 304, 350), "more_infos", 0) as need_details, pp.previsionnel_fin, pa.time, pa.consumed, pa.tmp_closed, "'.$ets.'" as ets, pre.code
	 									 FROM projet_affectation pa
	 									 INNER JOIN projet_prestation pp ON pa.projet_prestations_id = pp.id
	 									 INNER JOIN prestations pre ON pp.prestation_id = pre.id
	 									 INNER JOIN projet p ON pp.projet_id = p.id
	 									 INNER JOIN am_crm_entreprises e ON e.contact_id = p.client_id
	 									 WHERE pa.user_id = "'.$user_id.'"
	 									 AND pa.closed = 0
										 GROUP BY pp.id');

			while ($row = mysql_fetch_assoc($projets_stmt))
			{
				$affectations[$row['client'].'-'.$row['projet'].'-'.$row['prestation'].'-'.++$i] = $row;
				$affectations[$row['client'].'-'.$row['projet'].'-'.$row['prestation'].'-'.$i]['history_html'] = $this->_getHistory($row['affectation_id']);
				$affectations[$row['client'].'-'.$row['projet'].'-'.$row['prestation'].'-'.$i]['total_time'] = $this->_getHistorytotal($row['affectation_id']);
				$total_time_day[0] += $affectations[$row['client'].'-'.$row['projet'].'-'.$row['prestation'].'-'.$i]['total_time'];
				foreach($days as $day => $date)
				{
					$affectations[$row['client'].'-'.$row['projet'].'-'.$row['prestation'].'-'.$i]['total_time_days'][$day] = $this->_getHistorytotal($row['affectation_id'], $day);
					$affectations[$row['client'].'-'.$row['projet'].'-'.$row['prestation'].'-'.$i]['comment'][$day] = $this->_getComments($row['affectation_id'], $day);
					$total_time_day[$day] += $affectations[$row['client'].'-'.$row['projet'].'-'.$row['prestation'].'-'.$i]['total_time_days'][$day];
				}
			}
		}

		ksort($affectations);

		// Utile pour l'export Excel
		$this->_affectations = $affectations;
		$this->view->affectations = $this->_affectations;

		// Switch sur la base de Mulhouse
		mysql_select_db(DB_PREFIX.'mulhouse');
		mysql_query('SET NAMES "latin-1"');

		$common_activites = array();
		$result = mysql_query("SELECT * FROM common_activite");

		while($common_activite = mysql_fetch_assoc($result)) {
			if ($common_activite['id'] == 5) // Heures récupérées
				continue;

			$common_activite['name'] = utf8_encode($common_activite['name']);
			$common_activite['history_html'] = $this->_getHistory($common_activite['id'], $user_id);
			$common_activite['total_time'] = $this->_getHistorytotal($common_activite['id'], false, $user_id);
			$total_time_day[0] += $common_activite['total_time'];
			foreach($days as $day => $date)
			{
				$common_activite['total_time_days'][$day] = $this->_getHistorytotal($common_activite['id'], $day, $user_id);
				$total_time_day[$day] += $common_activite['total_time_days'][$day];
			}
			$common_activites[] = $common_activite;
		}

		$this->view->common_activites = $common_activites;
		$this->view->date = date('d/m/Y');
		$this->view->mois_liste = $this->_months_list;
		$years = array();
		for ($current_year = (int)date('Y'); $current_year > 2008; $current_year--)
			$years[] = $current_year;
		$this->view->annees_liste = array_reverse($years);
		$this->view->days = array_reverse(array_slice($days, 0, 6));
		$this->view->total_time_day = $total_time_day;
		$this->view->gestion = $gestion;
		$this->view->flashmessenger = $this->_flashMessenger->getMessages();
	}

	/**
	 *	Affecte à la vue le tableau des heures affectées par équipes et par ressource
	 *	Tableau de la forme :
	 *	[$teamName :[
	 *					'team' : [
	 *						$year: [
	 *							$month: [
	 *								'hours_available': $hoursAvailable,
	 *								'hours_planned': $hoursPlanned,
	 *								'hours_left': $hoursLeft
	 *							]
	 *						]
	 *					],
	 *					$userId: [
	 *						$year: [
	 *							$month: [
	 *								'hours_available': $hoursAvailable,
	 *								'hours_planned': $hoursPlanned,
	 *								'hours_left': $hoursLeft
	 *							]
	 *						],
	 *						'lastName': $lastName,
	 *						'firstName': $firstName,
	 *						'heures_jour': $heuresJours
	 *					],
	 *				]
	 *	]
	 */
	public function planningressourceAction(){
		//Récupération de la team de l'utilisateur actuel pour affichage par défaut
		$user_id = $this->user->contact_id;
		$db = new PDO('mysql:dbname='.DB_PREFIX.'common;host:localhost', DB_USER_NAME, DB_PASSWORD);
		$query_fetch_user_group = 'SELECT right_group_id FROM rights_groups_users WHERE user_id = :userId';
		$binds = array(
			':userId' => $user_id
		);
		$fetch_user_group = $db->prepare($query_fetch_user_group);
		$fetch_user_group->execute($binds);

		$groupId = 0;
		while($row_user_group = $fetch_user_group->fetch(PDO::FETCH_ASSOC)){
			$groupId = (int)$row_user_group['right_group_id'];
		}

		$authorizedUsers = array(1, 2, 3, 4);
		if(!in_array($groupId, $authorizedUsers)){
			die();
		}

		
		$query_fetch_user_team = 'SELECT equipe FROM am_crm_salaries WHERE contact_id = :userId';
		$binds = array(
			':userId' => $user_id
		);
		$fetch_user_team = $db->prepare($query_fetch_user_team);
		$fetch_user_team->execute($binds);

		$teamId = 0;
		while($row_user_team = $fetch_user_team->fetch(PDO::FETCH_ASSOC)){
			$teamId = (int)$row_user_team['equipe'];
		}

		$teamConditions = '';
		$printedTeams = array(1, 2, 3);
		if(in_array($printedTeams, $teamId)){
			$teamConditions = $teamId;
		}

		$sub_companies = explode(',', SUB_COMPANIES);

		$this->_helper->layout->assign('sub_menu',$this->menu->get('saisie.planning_ressource'));

		$startDate = new DateTime();
		$monthsAmountToPrint = 12;


		$month = (int)$startDate->format('m');
		$year = (int)$startDate->format('Y');
		$monthsToPrint = array();
		$usersArray = array();
		$resultArray = array();
		$teamsArray = array();

		for($actualMonthNumber = 0; $actualMonthNumber < $monthsAmountToPrint; $actualMonthNumber++){
			$monthsToPrint[$year][] = $month;

			$sql = '	SELECT 	pe.contact_id as userId,
								pe.nom as lastName,
								pe.prenom as firstName,
								eq.equipe as team,
								sa.heures_jour as heures_jour,
								(
									SELECT  SUM(mt.time) as cumulHeures
									FROM affectation_mensualTime mt
									INNER JOIN projet_affectation pa
										ON mt.projet_affectation_id = pa.id
									WHERE pa.user_id = pe.contact_id
									AND month = "'.$month.'"
									AND year = "'.$year.'"
									AND pa.closed = 0
									GROUP BY pa.user_id
								) AS tempsAffecte
						FROM am_crm_salaries sa
						INNER JOIN am_crm_personnes pe
							ON sa.contact_id = pe.contact_id
						INNER JOIN am_crm_salaries_equipes eq
							ON eq.id = sa.equipe
						WHERE eq.id IN ('.implode(', ', $printedTeams).')
						ORDER BY eq.id DESC, pe.prenom, pe.nom
			';

			mysql_select_db(DB_PREFIX.'mulhouse');
			$fetch_users = mysql_query($sql);

			while($row = mysql_fetch_assoc($fetch_users)){
				$userId = (int)$row['userId'];
				$teamName = utf8_encode($row['team']);

				/*$working_days = Absence_Model_Conge::getAbsences((int)$userId, $month, $year, 'mulhouse');
				$usersHours[$userId][$year][$month]['hours_available'] = 0;
				$usersHours[$userId][$year][$month]['hours_planned'] = 0;
				$usersHours[$userId][$year][$month]['hours_left'] = 0;

				foreach($working_days as $working_day){
					$usersHours[$userId][$year][$month]['hours_available'] += (float)$row['heures_jour'] * $working_day;
					$usersHours[$userId][$year][$month]['hours_left'] += (float)$row['heures_jour'] * $working_day;
				}*/

				/**************************RECUPERATION DES HEURES A TRAVAILLER***************************/
				$beginDate = new DateTime($year.'-'.$month.'-01');
				$stopDate = new DateTime($beginDate->format('Y-m-d'));
				$stopDate->modify('last day of this month');
				$absencesDetails = Absence_Model_Conge::getAbsencesDetails($userId, $beginDate, $stopDate, 'mulhouse');
				$usersHours[$userId][$year][$month]['hours_available'] = 0;
				$usersHours[$userId][$year][$month]['hours_planned'] = 0;
				$usersHours[$userId][$year][$month]['hours_left'] = 0;

				$beginYear = (int)$beginDate->format('Y');
				$beginMonth = (int)$beginDate->format('m');
				$beginDay = (int)$beginDate->format('d');
				$endYear = (int)$stopDate->format('Y');
				$endMonth = (int)$stopDate->format('m');
				$endDay = (int)$stopDate->format('d');

				for($actualYear = $beginYear; $actualYear <= $endYear; $actualYear++){
					$startMonth = ($actualYear == $beginYear) ? $beginMonth : 1;
					$stopMonth = ($actualYear == $endYear) ? $endMonth : 12;
					for($actualMonth = $startMonth; $actualMonth <= $stopMonth; $actualMonth++){
						$thisMonth = new DateTime($actualYear.'-'.$actualMonth.'-01');
						$daysInMonth = $thisMonth->format('t');
						$startDay = ($actualYear == $beginYear && $actualMonth == $beginMonth) ? $beginDay : 01;
						$stopDay = ($actualYear == $endMonth && $actualMonth == $endMonth) ? $endDay : $daysInMonth;
						for($actualDay = $startDay; $actualDay <= $stopDay; $actualDay++){
							if(empty($absencesDetails[$actualYear][$actualMonth][$actualDay])){
								$usersHours[$userId][$actualYear][$actualMonth]['hours_available'] += (float)$row['heures_jour'];
								$usersHours[$userId][$actualYear][$actualMonth]['hours_left'] += (float)$row['heures_jour'];
							}
							else{
								if($absencesDetails[$actualYear][$actualMonth][$actualDay]['type'] == 1){
									$usersHours[$userId][$actualYear][$actualMonth]['hours_available'] += (float)$row['heures_jour']-(float)$row['heures_jour'] * $absencesDetails[$actualYear][$actualMonth][$actualDay]['duration'];
									$usersHours[$userId][$actualYear][$actualMonth]['hours_left'] += (float)$row['heures_jour']-(float)$row['heures_jour'] * $absencesDetails[$actualYear][$actualMonth][$actualDay]['duration'];
								}
							}
						}
					}
				}
				/**************************RECUPERATION DES HEURES A TRAVAILLER***************************/

				$usersHours[$userId]['lastName'] = utf8_encode($row['lastName']);
				$usersHours[$userId]['firstName'] = utf8_encode($row['firstName']);
				$usersHours[$userId]['heures_jour'] = (float)$row['heures_jour'];
				$myDebugNumber = (float)$row['tempsAffecte'];
				$usersHours[$userId][$year][$month]['hours_planned'] = $myDebugNumber;
				$usersHours[$userId][$year][$month]['hours_left'] -= $myDebugNumber;

				if(empty($teamHours[$teamName][$year][$month]['hours_available']))
					$teamHours[$teamName][$year][$month]['hours_available'] = 0;
				$teamHours[$teamName][$year][$month]['hours_available'] += $usersHours[$userId][$year][$month]['hours_available'];

				if(empty($teamHours[$teamName][$year][$month]['hours_planned']))
					$teamHours[$teamName][$year][$month]['hours_planned'] = 0;
				$teamHours[$teamName][$year][$month]['hours_planned'] += $usersHours[$userId][$year][$month]['hours_planned'];

				if(empty($teamHours[$teamName][$year][$month]['hours_left']))
					$teamHours[$teamName][$year][$month]['hours_left'] = 0;
				$teamHours[$teamName][$year][$month]['hours_left'] += $usersHours[$userId][$year][$month]['hours_left'];

				$teamHours[$teamName]['lastName'] = $teamName;

				$resultArray[$teamName]['team'] = $teamHours[$teamName];
				$resultArray[$teamName][$userId] = $usersHours[$userId];

				if(!in_array($teamName, $teamsArray)){
					$teamsArray[] = $teamName;
				}
			}
			$month++;
			if($month > 12){
				$month = 1;
				$year++;
			}
		}

		$negativDifference = 0;
		$positivDifference = 0;

		foreach($resultArray as $usersHours){
			foreach($usersHours as $info => $userInfos){
				if($info == 'team')
					continue;
				foreach($monthsToPrint as $year => $months){
					foreach($months as $month){
						$difference = (int)($userInfos[$year][$month]['hours_available'] - $userInfos[$year][$month]['hours_planned']);

						if($difference < $negativDifference && $difference < 0)
							$negativDifference = $difference;
						if($difference > $positivDifference && $difference > 0)
							$positivDifference = $difference;
					}
				}
			}
		}

		/**
		 *	Traitement des couleurs des cases.
		 *	Ici, on peut définir la palette de teintes utilisée dans le nuancier en ajustant le chiffre divisé (entre 0 et 255)
		 *	Pour changer les couleurs, c'est dans la vue
		 */
		$negativGap = 100 / $negativDifference * -1;
		$positivGap = 255 / $positivDifference;


		$this->view->negativGap = $negativGap;
		$this->view->positivGap = $positivGap;
		$this->view->teamsArray = $teamsArray;
		$this->view->users = $resultArray;
		$this->view->monthsToPrint = $monthsToPrint;
	}

	public function saisiebetaAction(){
		/*$db = new PDO('mysql:dbname='.DB_NAME.';host:localhost', DB_USER_NAME, DB_PASSWORD);//Connection en PDO

		//Récupération des ID des affectations par user ID pour la gestion
		$query = 'SELECT id, user_id FROM projet_affectation WHERE projet_prestations_id = 11271;';
		$binds = array();
		$stmt = $db->prepare($query);
		$stmt->execute($binds);
		$affectationArray = array();
		while($row = $stmt->fetch(PDO::FETCH_ASSOC)){
			$affectationArray[(int)$row['user_id']]= (int)$row['id'];
		}

		var_dump($affectationArray);
		echo '<br><br>';

		$query = 'SELECT * FROM common_production WHERE common_activite_id = 4 AND time != 0 AND DATE(date) = DATE(:askedDay);';
		$binds = array(':askedDay' => '2016-07-13');
		$stmt = $db->prepare($query);
		$stmt->execute($binds);

		while($row = $stmt->fetch(PDO::FETCH_ASSOC)){
			$query2 = 'INSERT INTO projet_production(projet_affectation_id, `date`, `time`, `comment`) VALUES(:affId, :date, :time, :comment)';
			$binds2 = array(
				':affId' => $affectationArray[(int)$row['user_id']],
				':date' => $row['date'],
				':time' => (float)$row['time'],
				':comment' => utf8_encode($row['comment'])
			);
			$stmt2 = $db->prepare($query2);
			$stmt2->execute($binds2);
			var_dump($stmt2);
			echo '<br>';
			var_dump($binds2);
			echo '<br>';
		}*/

		/*$db = new PDO('mysql:dbname='.DB_NAME.';host:localhost', DB_USER_NAME, DB_PASSWORD);//Connection en PDO

		$query = 'SELECT * FROM affectation_dailyTime;';
		$binds = array();
		$stmt = $db->prepare($query);
		$stmt->execute($binds);
		$oldPlan = array();
		while($row = $stmt->fetch(PDO::FETCH_ASSOC)){
			$planObject = new Managing_DailyPlanning(0, $row['affectation_id'], new DateTime($row['begin_date']),
				$row['planned_hours'], $row['task'], 0, 2, new DateTime($row['end_date']), 1, $row['recurrence']);
			$oldPlan[] = $planObject;
		}

		Managing_DailyPlanning::insertMultiple($oldPlan, $db);*/
	}

	public function ajaxgetplanninginformationAction(){
		$response = $this->getResponse();

		$information = $this->getPlanningInformation(null, $this->_request->getParam('userId'));

		$this->view->assign('informations', $information['informations']);
		$this->view->assign('dailyHoursArray', $information['dailyHoursArray']);
		$this->view->assign('monthsInformations', $information['monthsInformations']);
		$this->view->assign('daysAmount', $information['daysAmount']);
		$this->view->assign('dailyPlanningDetails', $information['dailyPlanningDetails']);
		$this->view->assign('productionDetails', $information['productionDetails']);
		$this->view->assign('totalProduction', $information['totalProduction']);
		$this->view->assign('plannedHours', $information['plannedHours']);
		$this->view->assign('dailyHours', $information['dailyHours']);
		$this->view->assign('markersArray', $information['markersArray']);
		$this->view->assign('daysToPrint', $information['daysToPrint']);
		$this->view->assign('absences', $information['absences']);
		$this->view->assign('userId',  $information['userId']);
		$this->view->assign('firstDayToPrint',  $information['firstDayToPrint']);

		$this->render('tableplanification', 'tableplanification');

		$result = $response->getBody('tableplanification');
		die($result);
	}

	public function ajaxgetrowaffectationAction(){
		$affectationId = $this->_request->getParam('affectationId');
		$userId = $this->_request->getParam('userId');

		$response = $this->getResponse();

		$information = $this->getPlanningInformation($affectationId, $userId);

		$this->view->assign('informations', $information['informations']);
		$this->view->assign('dailyHoursArray', $information['dailyHoursArray']);
		$this->view->assign('monthsInformations', $information['monthsInformations']);
		$this->view->assign('daysAmount', $information['daysAmount']);
		$this->view->assign('dailyPlanningDetails', $information['dailyPlanningDetails']);
		$this->view->assign('productionDetails', $information['productionDetails']);
		$this->view->assign('totalProduction', $information['totalProduction']);
		$this->view->assign('plannedHours', $information['plannedHours']);
		$this->view->assign('dailyHours', $information['dailyHours']);
		$this->view->assign('markersArray', $information['markersArray']);
		$this->view->assign('daysToPrint', $information['daysToPrint']);
		$this->view->assign('absences', $information['absences']);
		$this->view->assign('userId',  $information['userId']);

		$this->render('rowaffectation', 'rowaffectation');

		$result = $response->getBody('rowaffectation');
		die($result);
	}

	public function ajaxgetheaderaffectationAction(){
		$response = $this->getResponse();

		$information = $this->getPlanningInformation();

		$this->view->assign('informations', $information['informations']);
		$this->view->assign('dailyHoursArray', $information['dailyHoursArray']);
		$this->view->assign('monthsInformations', $information['monthsInformations']);
		$this->view->assign('daysAmount', $information['daysAmount']);
		$this->view->assign('dailyPlanningDetails', $information['dailyPlanningDetails']);
		$this->view->assign('productionDetails', $information['productionDetails']);
		$this->view->assign('totalProduction', $information['totalProduction']);
		$this->view->assign('plannedHours', $information['plannedHours']);
		$this->view->assign('dailyHours', $information['dailyHours']);
		$this->view->assign('markersArray', $information['markersArray']);
		$this->view->assign('daysToPrint', $information['daysToPrint']);
		$this->view->assign('absences', $information['absences']);
		$this->view->assign('userId',  $information['userId']);
		$this->view->assign('firstDayToPrint',  $information['firstDayToPrint']);

		$this->render('rowheaderplanification', 'rowheaderplanification');

		$result = $response->getBody('rowheaderplanification');
		die($result);
	}

	private function getPlanningInformation($affectationId = null, $userId = null){
		//Debug temporaire pour supprimer la limite des 30 secondes
		set_time_limit(0);

		/*******************DEFINITION DE VARIABLES D'ENVIRONMENT*****************/
		$db = new PDO('mysql:dbname='.DB_NAME.';host:localhost', DB_USER_NAME, DB_PASSWORD);//Connection en PDO
		if($userId == null){
			$userId = $this->user->contact_id;//Récupération user
		}
		if($userId == 61020){
			//$userId = 18775;
			//$userId = 48560;
		}
		$informations = array();//Tableau d'informations
		$nbMonthsToPrint = 1;//Nombre de mois à afficher après le mois en cours
		$nbDaysInCurrentMonth = 40;//Nombre de jours à afficher pour la définition du mois en cours
		$nbDaysInHoursEntry = 4;
		$dailyHours = 0;
		$markersArray = array();//Tableau des jalons

		//Plus vieille date de la definition (Variable servant surtout à chercher les absences, la saisie et la planification)
		$oldestDate = new Managing_DateTime();
		for($i = 0; $i < $nbDaysInHoursEntry; $i++){
			$oldestDate->modify('-1 days');
			if($oldestDate->format('N') == 7){
				$oldestDate->modify('-2 days');
			}
		}
		$firstDayOfMonth = new DateTime();
		$firstDayOfMonth->modify('first day of this month');
		//On regarde la différence avec le début du mois
		$diff = $oldestDate->diff($firstDayOfMonth);
		//Et on prend la date la plus petite
		if($diff->format('%R') == '-'){
			$oldestDate = $firstDayOfMonth;
		}
		//Plus récente date de la definition (Variable servant surtout à chercher les absences, la saisie et la planification)
		$newestDate = new Managing_DateTime();
		$newestDate->modify('+'.$nbMonthsToPrint.' month');
		$newestDate->modify('last day of this month');

		$absencesDetails = Absence_Model_Conge::getAbsencesDetails($userId, $oldestDate, $newestDate, 'mulhouse');
		/*******************DEFINITION DE VARIABLES D'ENVIRONMENT*****************/

		/*******************RECUPERATION DU NOMBRE D'HEURES A TRAVAILLER PAR JOUR*****************/
		$query_fetch_dailyHours = 'SELECT heures_jour AS dailyHours FROM am_crm_salaries WHERE contact_id = :userId';
		$binds_fetch_dailyHours = array(
			':userId' => $userId
		);
		$stmt_fetch_dailyHours = $db->prepare($query_fetch_dailyHours);
		$stmt_fetch_dailyHours->execute($binds_fetch_dailyHours);
		while($row_dailyHours = $stmt_fetch_dailyHours->fetch(PDO::FETCH_ASSOC)){
			$dailyHours = (float)$row_dailyHours['dailyHours'];
			$informations['dailyHours'] = $dailyHours;
		}
		/*******************RECUPERATION DU NOMBRE D'HEURES A TRAVAILLER PAR JOUR*****************/

		//Requete de sélection de toutes les affectations liées à la personne qui ne sont pas encore fermées
		$query_fetch_affectations = 'SELECT aff.id AS affectationId,
											pres.name AS prestationName,
											pres.id AS prestationId,
											proj.nom AS projectName,
											proj.id AS projectId,
											(aff.time - aff.consumed) AS timeLeft,
											affmt.time AS plannedTime,
											affmt.year AS year,
											affmt.month AS month,
											affmt.comment AS comment,
											ent.raison_sociale as companyName,
											presta.activite_id as activite,
											IF(pres.prestation_id IN (109, 94, 124, 125, 205, 304, 350), 1, 0) as need_details
									FROM projet_affectation aff
									LEFT JOIN affectation_mensualTime affmt
										ON affmt.projet_affectation_id = aff.id
									INNER JOIN projet_prestation pres
										ON pres.id = aff.projet_prestations_id
									INNER JOIN projet proj
										ON proj.id = pres.projet_id
									INNER JOIN am_crm_entreprises ent
										ON ent.contact_id = proj.client_id
									INNER JOIN prestations presta
										ON presta.id = pres.prestation_id
									WHERE aff.user_id = :userId
									AND aff.closed = 0
									'.($affectationId != null ? 'AND aff.id = :affectationId' : '').'
									ORDER BY ent.raison_sociale;';
		$binds = array(
			':userId' => $userId,
		);
		if($affectationId != null){
			$binds[':affectationId'] = $affectationId;
		}
		$stmt_fetch_affectations = $db->prepare($query_fetch_affectations);
		$stmt_fetch_affectations->execute($binds);
		$affectationsArray = array();
		$hoursForThisAffectation = array();
		while($row_affectations = $stmt_fetch_affectations->fetch(PDO::FETCH_ASSOC)){
			$projectId = (int)$row_affectations['projectId'];
			$prestationId = (int)$row_affectations['prestationId'];
			//Stockage des heures planifiées
			$plannedTime = (float)$row_affectations['plannedTime'];
			$newPlannedTime = $plannedTime - (int)$plannedTime;
			if($newPlannedTime <= 0.13 || $newPlannedTime > 0.87){
				$plannedTime = (int)$plannedTime;
			}
			elseif($newPlannedTime > 0.13 && $newPlannedTime <= 0.37){
				$plannedTime = (int)$plannedTime + 0.25;
			}
			elseif($newPlannedTime > 0.37 && $newPlannedTime <= 0.67){
				$plannedTime = (int)$plannedTime + 0.5;
			}
			else{
				$plannedTime = (int)$plannedTime + 0.75;
			}
			$plannedHours[$prestationId][(int)$row_affectations['year']][(int)$row_affectations['month']] = $plannedTime;
			$hoursForThisAffectation[(int)$row_affectations['affectationId']][(int)$row_affectations['year']][(int)$row_affectations['month']] = $plannedTime;
			$comments[$prestationId][(int)$row_affectations['year']][(int)$row_affectations['month']] = utf8_encode($row_affectations['comment']);

			//Stockage des informations liées à la prestation
			$prestationsInformations[$projectId][$prestationId] = array(
				'prestationName' => utf8_encode($row_affectations['prestationName']),
				'comment' => $comments[$prestationId],
				'affectationId' => (int)$row_affectations['affectationId'],
				'timeLeft' => (float)$row_affectations['timeLeft'],
				'plannedHours' => $plannedHours[$prestationId],
				'activite' => (int)$row_affectations['activite'],
				'need_details' => (int)$row_affectations['need_details']
			);

			//Stockage des informations du projet

			$projectInformations[$projectId] = array(
				'projectName' => utf8_encode($row_affectations['projectName']),
				'prestations' => $prestationsInformations[$projectId],
				'companyName' => utf8_encode($row_affectations['companyName'])
			);

			$informations['projects'] = $projectInformations;
			$affectationsArray[(int)$row_affectations['affectationId']] = array();
		}

		/***************************GESTION DES HEURES QUOTIDIENNES PLANIFIEES**************************/
		$dailyPlanning = Managing_DailyPlanning::fetchByUserId($userId);
		foreach($dailyPlanning as $key => $dp){
			$dailyPlanning[$key]->setExceptions(Managing_DailyPlanningException::fetchByPlanningId($dp->getId()));
		}
		/***************************GESTION DES HEURES QUOTIDIENNES PLANIFIEES**************************/

		/***************************GESTION DES DATES AFFICHEES ET DES HEURES A TRAVAILLER**************************/
		$today = new Managing_DateTime();
		$beginDate = new Managing_DateTime($today->format('Y-m-d'));
		for($i = 0; $i <= $nbDaysInHoursEntry; $i++){
			$beginDate->modify('-1 days');
			if($beginDate->format('N') == 7){
				$beginDate->modify('-2 days');
			}
		}

		$endDate = new Managing_DateTime($today->format('Y-m-d'));
		$endDate = $endDate->modify('+'.$nbMonthsToPrint.' month');
		$endDate->modify('last day of this month');

		$printedMonths = array();
		$printedMonths[(int)$today->format('Y')][] = (int)$today->format('m');
		$nextMonth = $today->modify('+'.$nbMonthsToPrint.' month');
		$printedMonths[(int)$nextMonth->format('Y')][] = (int)$nextMonth->format('m');

		$startYear = (int)$beginDate->format('Y');
		$stopYear = (int)$endDate->format('Y');
		$dailyHoursArray = array();
		$pastDailyHoursArray = array();
		$monthsInformations = array();
		$daysAmount = 0;
		$crawledDates = array();
		$absences = array();

		$today = new DateTime();
		$today = new DateTime($today->format('Y-m-d'));

		for($actualYear = $startYear; $actualYear <= $stopYear; $actualYear++){
			$startMonth = ($actualYear == $startYear) ? (int)$beginDate->format('m') : 1;
			$stopMonth = ($actualYear == $stopYear) ? (int)$endDate->format('m') : 12;
			for($actualMonth = $startMonth; $actualMonth <= $stopMonth; $actualMonth++){
				if($actualYear == $beginDate->format('Y') && $actualMonth == $beginDate->format('m')){
					$actualBeginDate = new Managing_DateTime($actualYear.'-'.$actualMonth.'-'.$beginDate->format('d'));
					$actualEndDate = new Managing_DateTime($actualBeginDate->format('Y-m-d'));
					$actualEndDate->modify('+'.$nbDaysInCurrentMonth.' days');
				}else{
					$actualBeginDate = new Managing_DateTime($actualYear.'-'.$actualMonth.'-01');
					$actualEndDate = new Managing_DateTime($actualBeginDate->format('Y-m-d'));
					$actualEndDate->modify('last day of this month');
				}
				$daysInMonth = Managing_DateTime::getDaysBetween($actualBeginDate, $actualEndDate);
				$totalHours = 0;

				foreach($daysInMonth as $crawledYear => $months){
					foreach($months as $crawledMonth => $days){
						foreach($days as $crawledDay){
							$crawledDate = new DateTime($crawledYear.'-'.$crawledMonth.'-'.$crawledDay);
							if($crawledDate->format('N') >= 6)
								continue;
							$crawledDates[$crawledYear][$crawledMonth][] = $crawledDay;

							if(empty($monthsInformations[$actualYear][$actualMonth]['daysAmount']))
								$monthsInformations[$actualYear][$actualMonth]['daysAmount'] = 0;
							$monthsInformations[$actualYear][$actualMonth]['daysAmount']++;
							$daysAmount++;

							$hoursToWorkThisDay = 0;

							if(empty($absencesDetails[(int)$crawledYear][(int)$crawledMonth][(int)$crawledDay])){
								$hoursToWorkThisDay = $informations['dailyHours'];
								$absences[(int)$crawledYear][(int)$crawledMonth][(int)$crawledDay] = 0;
							}else{
								if($absencesDetails[(int)$crawledYear][(int)$crawledMonth][(int)$crawledDay]['type'] == 1){
									$hoursToWorkThisDay = $informations['dailyHours'] - $absencesDetails[(int)$crawledYear][(int)$crawledMonth][(int)$crawledDay]['duration'] * $informations['dailyHours'];
									$absences[(int)$crawledYear][(int)$crawledMonth][(int)$crawledDay] = 0;
									if($hoursToWorkThisDay == 0){
										$hoursToWorkThisDay = -9000;
										$absences[(int)$crawledYear][(int)$crawledMonth][(int)$crawledDay] = 1;
									}
								}else{
									$hoursToWorkThisDay = -9000;
									$absences[(int)$crawledYear][(int)$crawledMonth][(int)$crawledDay] = 1;
								}
							}
							$dailyHoursArray[$actualYear][$actualMonth][$crawledYear][$crawledMonth][$crawledDay] = $hoursToWorkThisDay;
							$hoursToWorkThisDay = $hoursToWorkThisDay < 0 ? 0 : $hoursToWorkThisDay;
							if($actualYear == $crawledYear && $actualMonth == $crawledMonth)
								$totalHours += $hoursToWorkThisDay;

						}
					}
				}

				$informations['available_hours'][$actualYear][$actualMonth] = $totalHours;
			}
		}
		/***************************GESTION DES DATES AFFICHEES ET DES HEURES A TRAVAILLER**************************/

		$dailyPlanningDetails = Managing_DailyPlanning::getDailyPlanningDetails($dailyPlanning, $crawledDates, $absencesDetails);

		for($actualYear = $startYear; $actualYear <= $stopYear; $actualYear++){ //for actualyear < stopyear
			$startMonth = ($actualYear == $startYear) ? (int)$beginDate->format('m') : 1;
			$stopMonth = ($actualYear == $stopYear) ? (int)$endDate->format('m') : 12;
			for($actualMonth = $startMonth; $actualMonth <= $stopMonth; $actualMonth++){ //for actualMonth < stopMonth
				if($actualYear == $beginDate->format('Y') && $actualMonth == $beginDate->format('m')){
					$actualBeginDate = new Managing_DateTime($actualYear.'-'.$actualMonth.'-'.$beginDate->format('d'));
					$actualEndDate = new Managing_DateTime($actualBeginDate->format('Y-m-d'));
					$actualEndDate->modify('+'.$nbDaysInCurrentMonth.' days');
				}else{
					$actualBeginDate = new Managing_DateTime($actualYear.'-'.$actualMonth.'-01');
					$actualEndDate = new Managing_DateTime($actualBeginDate->format('Y-m-d'));
					$actualEndDate->modify('last day of this month');
				}
				$daysInMonth = Managing_DateTime::getDaysBetween($actualBeginDate, $actualEndDate);

				foreach($daysInMonth as $crawledYear => $months){ //foreach daysInMonth
					foreach($months as $crawledMonth => $days){ //foreach months
						foreach($days as $crawledDay){ //foreach days
							$crawledDate = new DateTime($crawledYear.'-'.$crawledMonth.'-'.$crawledDay);
							if($crawledDate->format('N') >= 6)
								continue;

							foreach($dailyPlanningDetails as $affectationId => $planningInformation){ //foreach dailyPlanningDetails
								if(!empty($planningInformation[$crawledYear][$crawledMonth][$crawledDay])){
									foreach($planningInformation[$crawledYear][$crawledMonth][$crawledDay] as $planningObject){
										$planningObject = $planningObject;

										$dailyHoursArray[$actualYear][$actualMonth][$crawledYear][$crawledMonth][$crawledDay] -= $planningObject->getAffectedTime();

										if($crawledDate >= $today){
											$informations['available_hours'][$actualYear][$actualMonth] -= $planningObject->getAffectedTime();
											if($actualYear == $crawledYear && $actualMonth == $crawledMonth)
												$hoursForThisAffectation[$affectationId][(int)$actualYear][(int)$actualMonth] -= $planningObject->getAffectedTime();
										}
									}
								}
							} //foreach dailyPlanningDetails
						} //foreach days
					} //foreach months
				} //foreach daysInMonth
			} //for actualMonth < stopMonth
		} //for actualyear < stopyear

		/***********************RECUPERATION DES JALONS*****************************/
		$query_fetch_markers = '
			SELECT markers.*
			FROM affectation_markers markers
			INNER JOIN projet_affectation aff
				ON aff.id = markers.affectation_id
			WHERE aff.user_id = :userId
			AND markers.date >= :beginDate
			AND markers.date <= :endDate
		';
		$binds = array(
			':userId' => $userId,
			':beginDate' => $beginDate->format('Y-m-d'),
			':endDate' => $endDate->format('Y-m-d')
		);

		$stmt_fetch_markers = $db->prepare($query_fetch_markers);
		$stmt_fetch_markers->execute($binds);

		while($row_markers = $stmt_fetch_markers->fetch(PDO::FETCH_ASSOC)){
			$markerDate = new DateTime($row_markers['date']);
			$affectationId = (int)$row_markers['affectation_id'];
			$markerName = $row_markers['name'];
			$markerId = (int)$row_markers['id'];
			$markerTreated = (bool)$row_markers['treated'];

			$markerYear = (int)$markerDate->format('Y');
			$markerMonth = (int)$markerDate->format('m');
			$markerDay = (int)$markerDate->format('d');

			$markersArray[$markerYear][$markerMonth][$markerDay][$affectationId] = array(
				'id' => $markerId,
				'name' => utf8_encode($markerName),
				'date' => $markerDate->format('d/m/Y'),
				'treated' => $markerTreated
			);
		}
		/***********************RECUPERATION DES JALONS*****************************/

		/***********************RECUPERATION DE LA SAISIE D'HEURES*****************************/
		$beginEntryDate = new DateTime();
		for($i = 0; $i < $nbDaysInHoursEntry; $i++){
			$beginEntryDate->modify('-1 days');
			if($beginEntryDate->format('N') == 7){
				$beginEntryDate->modify('-2 days');
			}
		}

		$endEntryDate = new DateTime();

		$firstDayToPrint = new DateTime($beginEntryDate->format('Y-m-d'));
		$firstDayToPrint->modify('-1 days');
		if($firstDayToPrint->format('N') == 7){
			$firstDayToPrint->modify('-2 days');
		}
		$firstDayEntry = array(
			'year' => $firstDayToPrint->format('Y'),
			'month' => $firstDayToPrint->format('m'),
			'day' => $firstDayToPrint->format('d')
		);


		$daysArray = Managing_DateTime::getDaysBetween($beginEntryDate, $endEntryDate);
		$daysToPrint = array();
		foreach($daysArray as $year => $months){
			foreach($months as $month => $days){
				foreach($days as $day){
					$crawledDate = new DateTime($year.'-'.$month.'-'.$day);
					if($crawledDate->format('N') < 6){
						if(empty($absencesDetails[(int)$year][(int)$month][(int)$day])){
							$absences[(int)$year][(int)$month][(int)$day] = 0;
						}else{
							if($absencesDetails[(int)$year][(int)$month][(int)$day]['type'] == 1){
								if($absencesDetails[(int)$year][(int)$month][(int)$day]['duration'] == 0.5){
									$absences[(int)$year][(int)$month][(int)$day] = 0;
								}
								else{
									$absences[(int)$year][(int)$month][(int)$day] = 1;
								}
							}else{
								$absences[(int)$year][(int)$month][(int)$day] = 1;
							}
						}

						$daysToPrint[(int)$year][(int)$month][(int)$day] = 1;
					}
				}
			}
		}

		$firstDayOfMonth = new DateTime();
		$firstDayOfMonth->modify('first day of this month');
		//On regarde la différence avec le début du mois
		$diff = $beginEntryDate->diff($firstDayOfMonth);

		//Et on prend la date la plus petite
		if($diff->format('%R') == '-'){
			$beginEntryDate = $firstDayOfMonth;
		}

		$daysArray = Managing_DateTime::getDaysBetween($beginEntryDate, $endEntryDate);

		//Récupération des heures saisies entre la date de début et celle de fin
		$query = '
			SELECT YEAR(prod.date) as prodYear, MONTH(prod.date) as prodMonth, DAY(prod.date) as prodDay, SUM(prod.time) as timeEntered, aff.id as affectationId
			FROM projet_production prod
			INNER JOIN projet_affectation aff
				ON aff.id = prod.projet_affectation_id
			WHERE user_id = :userId
			AND DATE(prod.date) >= DATE(:beginDate)
			AND DATE(prod.date) <= DATE(:endDate)
			GROUP BY aff.id, prod.date
		';
		$binds = array(
			':userId' => $userId,
			':beginDate' => $beginEntryDate->format('Y-m-d'),
			':endDate' => $endEntryDate->format('Y-m-d')
		);
		$stmt_fetch_production = $db->prepare($query);
		$stmt_fetch_production->execute($binds);
		while($row_production = $stmt_fetch_production->fetch(PDO::FETCH_ASSOC)){
			if((int)$firstDayEntry['year'] == (int)$row_production['prodYear'] && (int)$firstDayEntry['month'] == (int)$row_production['prodMonth'] && (int)$firstDayEntry['day'] == (int)$row_production['prodDay']){
				if(!isset($firstDayEntry['affectations'][(int)$row_production['affectationId']])){
					$firstDayEntry['affectations'][(int)$row_production['affectationId']] = 0;
				}
				if(!isset($firstDayEntry['total'])){
					$firstDayEntry['total'] = 0;
				}
				$firstDayEntry['affectations'][(int)$row_production['affectationId']] += (float)$row_production['timeEntered'];
				$firstDayEntry['total'] += (float)$row_production['timeEntered'];
			}
		}

		$array_production = array();
		$total_production = array();
		foreach($daysArray as $year => $months){
			foreach($months as $month => $days){
				foreach($days as $day){
					$crawledDate = new DateTime($year.'-'.$month.'-'.$day);
					if($crawledDate->format('N') == 6 || $crawledDate->format('N') == 7)
						continue;

					if(empty($total_production[(int)$year][(int)$month][(int)$day])){
						$total_production[(int)$year][(int)$month][(int)$day] = 0;
					}

					if(empty($array_production[(int)$year][(int)$month][(int)$day])){
						$array_production[(int)$year][(int)$month][(int)$day] = array();
					}

					$stmt_fetch_production = $db->prepare($query);
					$stmt_fetch_production->execute($binds);
					while($row_production = $stmt_fetch_production->fetch(PDO::FETCH_ASSOC)){
						if((int)$year == (int)$row_production['prodYear'] && (int)$month == (int)$row_production['prodMonth'] && (int)$day == (int)$row_production['prodDay']){
							$array_production[(int)$year][(int)$month][(int)$day][$row_production['affectationId']] = (float)$row_production['timeEntered'];
							$total_production[(int)$year][(int)$month][(int)$day] += (float)$row_production['timeEntered'];
							$hoursForThisAffectation[$row_production['affectationId']][(int)$year][(int)$month] -= (float)$row_production['timeEntered'];
						}
					}
				}
			}
		}
		/***********************RECUPERATION DE LA SAISIE D'HEURES*****************************/

		return array(
			'informations' => $informations,
			'dailyHoursArray' => $dailyHoursArray,
			'monthsInformations' => $monthsInformations,
			'daysAmount' => $daysAmount,
			'dailyPlanningDetails' => $dailyPlanningDetails,
			'productionDetails' => $array_production,
			'totalProduction' => $total_production,
			'plannedHours' => $hoursForThisAffectation,
			'dailyHours' => $dailyHours,
			'markersArray' => $markersArray,
			'daysToPrint' => $daysToPrint,
			'absences' => $absences,
			'userId' => $userId,
			'firstDayToPrint' => $firstDayEntry
		);
	}

	public function ajaxgetmodalmarkersinformationAction(){
		$response = $this->getResponse();
		$db = new PDO('mysql:dbname='.DB_NAME.';host:localhost', DB_USER_NAME, DB_PASSWORD);//Connection en PDO

		$markersIds = json_decode($this->_request->getParam('markersId'));

		$ids = str_repeat('?,', count($markersIds) - 1). '?';
		$query_fetch_markers = '
			SELECT marker.name as markerName, marker.date as markerDate, marker.id as markerId, marker.treated as treated, pres.name as presName, proj.nom as projectName, ent.raison_sociale as clientName
			FROM affectation_markers marker
			INNER JOIN projet_affectation aff
				ON aff.id = marker.affectation_id
			INNER JOIN projet_prestation pres
				ON pres.id = aff.projet_prestations_id
			INNER JOIN projet proj
				ON proj.id = pres.projet_id
			INNER JOIN am_crm_entreprises ent
				ON ent.contact_id = proj.client_id
			WHERE marker.id IN('.$ids.')
			AND marker.treated = 0
			AND marker.reprograming_date IS NULL
		;';

		$stmt_fetch_markers = $db->prepare($query_fetch_markers);
		$stmt_fetch_markers->execute($markersIds);

		$markersArray = array();
		while($row_markers = $stmt_fetch_markers->fetch(PDO::FETCH_ASSOC)){
			$markersArray[utf8_encode($row_markers['clientName'])][utf8_encode($row_markers['projectName'])][utf8_encode($row_markers['presName'])][] = array(
				'name' => utf8_encode($row_markers['markerName']),
				'date' => new DateTime($row_markers['markerDate']),
				'id' => (int)$row_markers['markerId'],
				'treated' => (bool)$row_markers['treated']
			);
		}

		$this->view->assign('markers', $markersArray);

		$this->render('modaldealwithmarkers', 'modaldealwithmarkers');

		$result = $response->getBody('modaldealwithmarkers');
		die($result);
	}

	public function ajaxdealwithmarkerAction(){
		$db = new PDO('mysql:dbname='.DB_NAME.';host:localhost', DB_USER_NAME, DB_PASSWORD);//Connection en PDO
		$markersToDealWith = $this->_request->getParam('dealWithMarker');
		$markersToModify = $this->_request->getParam('modifyMarker');
		$modified = true;

		$markersToValidate = array();
		foreach($markersToDealWith as $markerId => $isValidated){
			if((bool)$isValidated){
				$markersToValidate[] = (int)$markerId;
			}
		}

		if(!empty($markersToValidate)){
			$ids = str_repeat('?,', count($markersToValidate) - 1).'?';
			$query_update_markers = 'UPDATE affectation_markers SET treated = 1 WHERE id IN('.$ids.');';
			$stmt_update_markers = $db->prepare($query_update_markers);
			$modified = $stmt_update_markers->execute($markersToValidate);

			if(!$modified)
				die(json_encode(array(false, 'Le traitement des jalons a &eacute;chou&eacute;')));
		}

		/* MISE EN COMMENTAIRE CAR PAS BESOIN POUR LE MOMENT DU REPORT DES JALONS
		foreach($markersToModify as $markerId => $newDate){
			$query_update_marker = 'UPDATE affectation_markers SET reprograming_date = :newDate WHERE id = :id;';
			$binds = array(
				':newDate' => $newDate,
				':id' => (int)$markerId
			);
			$stmt_update_marker = $db->prepare($query_update_marker);
			$modified = $stmt_update_marker->execute($binds);
			if(!$modified)
				die(json_encode(array(false, 'Le traitement des jalons a &eacute;chou&eacute;')));
		}*/

		if($modified)
			die(json_encode(array(true, 'Jalons Trait&eacute;s')));
		else
			die(json_encode(array(false, 'Le traitement des jalons a &eacute;chou&eacute;')));
	}

	public function ajaxgetentryatthisdayAction(){
		$db = new PDO('mysql:dbname='.DB_NAME.';host:localhost', DB_USER_NAME, DB_PASSWORD);//Connection en PDO

		$askedDate = new DateTime($this->_request->getParam('selectedDate'));
		$userId = (int)$this->_request->getParam('userId');

		$date = new DateTime($askedDate->format('Y-m-d'));

		$query_fetch_user_hours = 'SELECT heures_jour AS dailyHours FROM am_crm_salaries WHERE contact_id = :userId';
		$binds = array(':userId' => $userId);
		$stmt_fetch_user_hours = $db->prepare($query_fetch_user_hours);
		$stmt_fetch_user_hours->execute($binds);

		$dailyHours = 0;
		while($row_user_hours = $stmt_fetch_user_hours->fetch(PDO::FETCH_ASSOC)){
			$dailyHours = (float)$row_user_hours['dailyHours'];
		}

		$query_fetch_enteredHours = '
			SELECT SUM(prod.time) as timeEntered, aff.id as affectationId
			FROM projet_production prod
			INNER JOIN projet_affectation aff
				ON aff.id = prod.projet_affectation_id
			WHERE DATE(prod.date) = DATE(:date)
			AND aff.user_id = :userId
			GROUP BY aff.id, prod.date
		;';
		$binds_fetch_enteredHours = array(
			':date' => $date->format('Y-m-d'),
			':userId' => $userId
		);

		$stmt_fetch_enteredHours = $db->prepare($query_fetch_enteredHours);
		$stmt_fetch_enteredHours->execute($binds_fetch_enteredHours);

		$hours = array();
		while($row_enteredHours = $stmt_fetch_enteredHours->fetch(PDO::FETCH_ASSOC)){
			$hours[(int)$row_enteredHours['affectationId']] = (float)$row_enteredHours['timeEntered'];
		}
		$totalTime = $dailyHours;
		foreach($hours as $aff => $time){
			$totalTime -= $time;
		}

		$colHeader = $totalTime;
		if($totalTime > 0){
			$headerClass = 'hours-planned-not-enough';
		}
		elseif($totalTime < 0){
			$headerClass = 'hours-planned-too-much';
		}
		else{
			$headerClass = 'hours-planned-ok';
		}
		$colHeaderClass = $headerClass;

		$response = array(
			'colHeader' => $colHeader,
			'colHeaderClass' => $colHeaderClass,
			'colHours' => array()
		);

		foreach($hours as $affectationId => $time){
			$response['colHours'][$affectationId] = $time;
		}
		

		die(json_encode($response));
	}

	/*public function ajaxrefreshtdAction(){
		$userId = $this->_request->getParam('userId');
		$year = $this->_request->getParam('year');
		$month = $this->_request->getParam('month');
		$dailyHours = $this->_request->getParam('dailyHours');

		$working_days = Absence_Model_Conge::getWorkingDays((int)$userId, $month, $year, 'mulhouse');
		$hours_available = 0;
		foreach($working_days as $working_day){
			$hours_available += $dailyHours * $working_day;
		}

		die(json_encode(array('maxHours'=> $hours_available)));
	}*/

	private function _getHistory($task_id, $user_id = false)
	{
		$html = false;

		$query = "SELECT SUM(time) AS production, DATE_FORMAT(date,'%d/%m/%y') AS dt
				  FROM ".($user_id ? "common_production" : "projet_production")."
				  WHERE ".($user_id ? "common_activite_id = ".(int)$task_id." AND user_id = ".(int)$user_id : "projet_affectation_id = ".(int)$task_id)." 
				  AND date > DATE_SUB(NOW(), INTERVAL 3 MONTH)
				  GROUP BY date
				  ORDER BY date DESC";

		$result = mysql_query($query);
		
		while($saisie = mysql_fetch_assoc($result))
			$html .= '<span>'.$saisie['dt'].'</span> : '.$saisie['production'].' h<br/>';

		return $html;
	}

	private function _getHistorytotal($task_id, $day = false, $user_id = false)
	{
		$total = 0;

		$query = "SELECT SUM(time) AS production
				  FROM ".($user_id ? "common_production" : "projet_production")."
				  WHERE ".($user_id ? "common_activite_id = ".(int)$task_id." AND user_id = ".(int)$user_id : "projet_affectation_id = ".(int)$task_id)." 
				  AND ".($day ? "date = '".$day."'" : "date > DATE_SUB(NOW(), INTERVAL 1 DAY)")."
				  GROUP BY date
				  ORDER BY date DESC";
		
		$result = mysql_query($query);
		
		while($saisie = mysql_fetch_assoc($result))
			$total += $saisie['production'];

		return $total;
	}

	private function _getComments($task_id, $day = false)
	{
		$comment = '';

		$query = "SELECT comment
				  FROM projet_production
				  WHERE projet_affectation_id = ".(int)$task_id." 
				  AND ".($day ? "date = '".$day."'" : "date > DATE_SUB(NOW(), INTERVAL 1 DAY)")."
				  LIMIT 0, 1";
		
		$result = mysql_query($query);
		
		$saisie = mysql_fetch_assoc($result);
		$comment = $saisie['comment'];

		return $comment;
	}

	/*private function _sendFinishNotification($projet_prestations_array, $sub_company)
	{
		// global $__section;
		
		// réécriture de la requête SQL le 10/06/20132 par CFR
		// afin d'obtenir un résultat pour les prestations où il n'y a aucune saisie,
		// et donc obtenir également un courriel
		$result = mysql_query($query = "SELECT
			p.nom,
			pp.name,
			pp.projet_id,
			pp.prestation_id,
			CONCAT(pers.prenom, ' ', pers.nom) AS username
		FROM
			projet p
		LEFT JOIN
			projet_prestation pp ON pp.projet_id = p.id 
		LEFT JOIN
			projet_affectation pa ON pa.projet_prestations_id = pp.id AND pa.tmp_closed = 0
		LEFT JOIN
			am_crm_personnes pers ON pers.contact_id = pa.user_id
		WHERE
			pa.id IN ('".join("','", $projet_prestations_array)."')");
		
		if(!mysql_num_rows($result)) return;

		$message = "";
		while ($_finish = mysql_fetch_assoc($result))
		{
			$message.= "projet : ".$_finish['nom']." prestation : ".$_finish['name']." - http://managing/".$sub_company."/Projet/Gestion/edit/id/".$_finish['projet_id']." \n";
			$last = $_finish;
		}
		
		$message = "Bonjour,\n\n".
			$last['username']." vous informe que les prestations suivantes sont terminées :\n\n".$message;
		
		$mail = new Zend_Mail();
		
		$mail->setBodyText($message);
		$mail->setFrom('managing@activis.net', 'Managing');
		$mail->addTo('aurelia.dutin@activis.net', 'Aurélia Dutin');
		$mail->setSubject('[managing] Notification de prestation(s) terminée(s)');
		$mail->send();
	}
	
	private function _sendNewAffectationNotification($projet_prestation_id, $sub_company)
	{
		// global $__section;
		if(!$projet_prestation_id) return;
		
		$result = mysql_query(
			"SELECT 
				projet.id as projet_id, 
				projet_prestation.name as prestation_name, 
				projet.nom as projet_name 
			FROM projet_prestation 
			LEFT JOIN projet ON projet.id = projet_prestation.projet_id 
			WHERE id = ".$projet_prestation_id
		) or die(mysql_error());
		
		$informations = mysql_fetch_assoc($result);
		$userName = $this->user->prenom.' '.$this->user->nom;

		$content = "Bonjour,\n\n".
			$userName.' souhaite être affecté à la prestation : '.$informations['prestation_name']."\n".
			"Projet : ".$informations['projet_name']."\n".
			"http://managing/".$sub_company."/Projet/Gestion/detail/id/".$informations['projet_id']."\n\n";
		
		$mail = new Zend_Mail();
		
		$mail->setBodyText($content);
		$mail->setFrom('managing@activis.net', 'Managing');
		$mail->addTo('aurelia.dutin@activis.net', 'Aurélia Dutin');
		$mail->setSubject('[managing] demande d\'affectation prestation');
		$mail->send();

	}*/
	
	/**
	 * Sauvegarde des heures saisies
	 * 
	 * @author Sully Din; Michael Hurni
	 */
	function saveAction()
	{
		$profil = Activis::registry('profil');

		$date_saisie = $this->_request->getParam('date');
		$all_comments = $this->_request->getParam('detail_maint');
		$all_comments_project = $this->_request->getParam('detail_project');
		$all_projet_prestations_array = $this->_request->getParam('finish', array('0' => '0'));
		
		$user = $this->_request->getParam('user');
		
		/**
		 * Sauvegarde des heures projet
		 */
		foreach($this->_request->getParam('saisie') as $sub_company => $saisies)
		{
			// Switch des bases de données
			mysql_select_db(DB_PREFIX.$sub_company);
			mysql_query('SET NAMES "latin-1"');

			Managing::switchDb($sub_company);

			$synthese = Activis::getNewModel("Synthese.Lines");
			$affectations = Activis::getNewModel("Projet.Projet_Affectations");
			$productions  = Activis::getNewModel("Projet.Projet_Productions");

			$comments = $all_comments[$sub_company];
			$comments_project = $all_comments_project[$sub_company];

			$projet_prestations_array = array_keys($all_projet_prestations_array[$sub_company]);
			
			// $this->_sendFinishNotification($projet_prestations_array[$sub_company], $sub_company);
			// $this->_sendNewAffectationNotification((int)$this->_request->getParam('prestation_id'), $sub_company);

			foreach($saisies as $affectation_id => $saisie_days)
			{
				foreach($saisie_days as $date_saisie => $heure)
				{
					$date_tmp = $this->_request->getParam('date');
					list($jour, $mois, $annee) =  explode('/', $date_tmp[$sub_company][$affectation_id]);
					$date = (empty($date_saisie) ? date('Y-m-d', mktime(0, 0, 0, $mois, $jour, $annee)) : $date_saisie);

					if(!empty($heure))
					{
						$affectation = $affectations->find($affectation_id)->current();
						
						$where = $productions->select()
								->where('projet_affectation_id = ?', $affectation->id)
								->where("date = '$date'");
								
						$production = $productions->fetchAll($where)->current();
						$heure = (float)str_replace(" ","",str_replace(",",".",$heure));
				
						if(!$production)
						{
							// Création d'une nouvelle ligne
							$production = $productions->fetchNew();
							$production->setFromArray(array(
								'projet_affectation_id' => $affectation_id,
								'date' => $date));
						}
						if (!empty($comments_project[$affectation_id][$date_saisie]))
							$production->comment = utf8_encode($comments_project[$affectation_id][$date_saisie]);
						$production->time += $heure;
						$production->save();
						$modified = true;
						
						$result = mysql_query("SELECT 
							SUM(projet_production.time) as view_affectation_time_consumed
							FROM projet_production 
							WHERE projet_production.projet_affectation_id = ".$affectation_id);
						$line = mysql_fetch_assoc($result);
						
						if(!empty($comments[$affectation_id][$date_saisie])) {
							mysql_query("INSERT INTO maintenances_history SET date = '". mysql_escape_string($date) ."',
								projet_affectation_id = $affectation_id, duration = '$heure', user = $user, 
								comment = '". mysql_real_escape_string($comments[$affectation_id][$date_saisie]) ."'");
							
						}
						
						$synthese->saveProduction($production);
						
						// $affectation->sendExceededNotification();
						$affectation->consumed = $line['view_affectation_time_consumed'];
						$affectation->save();
		                $affectation->checkAlerts();
		                
		                $projet = $affectation->getProject();
		                $projet->checkAlerts();
					}
				}
			}

			/*
			 * Modif Nartex 24/11/2011
			 * Sauvegarde de l'état "tmp_closed", en attendant la clôture officielle
			 */
			// Modif - JBO : clôture de la tâche automatique + clôture de la prestation automatique + passage du projet à 100% si toutes les prestations sont fermées
			mysql_query("UPDATE projet_affectation SET closed = 1 WHERE id IN ('".implode("','", $projet_prestations_array)."')");
			
			$presta_stmt = mysql_query("SELECT projet_prestations_id FROM projet_affectation WHERE id IN ('".implode("','", $projet_prestations_array)."') GROUP BY projet_prestations_id");
			$prestations = array();
			while ($row = mysql_fetch_assoc($presta_stmt))
			{
				$result = mysql_fetch_row(mysql_query("SELECT IF(SUM(pa.closed) = COUNT(pa.id), 1, 0) as closed_presta,
													   IF(pp.prestation_id IN (109,94,124,125,205,304,350), 1, 0) as maint_presta,
													   IF(pp.previsionnel_fin <= NOW(), 1, IF(SUM(pa.consumed) >= SUM(pa.time), 1, 0)) as finished_maint_presta
													   FROM projet_affectation pa
													   INNER JOIN projet_prestation pp ON pp.id = pa.projet_prestations_id
													   WHERE pa.projet_prestations_id = '".(int)$row['projet_prestations_id']."'
													   GROUP BY pa.projet_prestations_id"));
					
				if ($result[0] && !$result[1]) // Toutes les affectations sont fermées (hors maintenance non échue), on tag la prestation qui doit être fermée
					$prestations[] = $row['projet_prestations_id'];
				else if ($result[0] && $result[1] && $result[2]) // cas de figure des maintenances échues, on tag pour fermer aussi
					$prestations[] = $row['projet_prestations_id'];
			}

			if (!empty($prestations))
				mysql_query("UPDATE projet_prestation SET closed = 1, reel_fin = '".date('Y-m-d')."' WHERE id IN ('".implode("','", $prestations)."')");

			$projet_stmt = mysql_query("SELECT projet_id FROM projet_prestation WHERE id IN ('".implode("','", $prestations)."') GROUP BY projet_id");
			$projets = array();
			while ($row = mysql_fetch_assoc($projet_stmt))
			{
				$result = mysql_fetch_row(mysql_query("SELECT IF(SUM(closed) = COUNT(id), 1, 0) as closed_projet FROM projet_prestation WHERE projet_id = ".(int)$row['projet_id']." GROUP BY projet_id"));

				if ($result[0]) // Toutes les prestations sont fermées on tag le projet qui doit être à 100%
					$projets[] = $row['projet_id'];
			}

			if (!empty($projets))
				mysql_query("UPDATE projet SET avancement = 100 WHERE id IN ('".implode("','", $projets)."')");
			// End: modif JBO
		}

		/**
		 * Sauvegarde des heures pot commun (dans la base de Mulhouse)
		 */

		// Switch sur la bases de mulhouse
		mysql_select_db(DB_PREFIX.'mulhouse');
		mysql_query('SET NAMES "latin-1"');

		$common_comments = $this->_request->getParam('detail_common');

		foreach($this->_request->getParam('common_saisie') as $id => $data)
		{
			foreach($data['heure'] as $date_saisie => $heure)
			{
				if($heure)
				{
					$date = $data['date'];
					list($jour, $mois, $annee) = explode('/', $date);
					$date = (empty($date_saisie) ? date("Y-m-d", mktime(0, 0, 0, $mois, $jour, $annee)) : $date_saisie);
					if (!empty($common_comments[$id][$date_saisie]) || ((int)$profil === 10) || ((int)$profil === 11)) {
						$modified = true;
						mysql_query("INSERT INTO common_production (user_id, date, common_activite_id, time, comment) VALUES (".$this->_request->getParam('user_id').", '".$date."' , $id, '".str_replace(',', '.', $heure)."', '".mysql_real_escape_string($common_comments[$id][$date_saisie])."')");
					} else {
						$modified = false;
						$this->_flashMessenger->addMessage('Les heures non associables aux projets n\'ont pas &eacute;t&eacute; enregistr&eacute;es. Vous devez laisser un commentaire.');
					}
				}
			}
		}
		
	    $this->_flashMessenger->addMessage(
			($modified) ? 
				'Saisie sauvegard&eacute;e' : 
				'Aucune saisie n\'a &eacute;t&eacute; enregistr&eacute;e'
		);
		
		if ($this->_request->getParam('gestion'))
			$this->_redirect('/groupe/Projet/Saisie/gestion/user/'.$this->_request->getParam('user_id'));
		else
			$this->_redirect('/groupe/Projet/Saisie/index');
	}

	function savewithoutredirectAction()
	{
		$profil = Activis::registry('profil');

		$date_saisie = $this->_request->getParam('date');
		$all_comments = $this->_request->getParam('detail_maint');
		$all_comments_project = $this->_request->getParam('detail_project');
		$all_projet_prestations_array = $this->_request->getParam('finish', array('0' => '0'));

		$user = $this->_request->getParam('user');

		/**
		 * Sauvegarde des heures projet
		 */
		foreach($this->_request->getParam('saisie') as $sub_company => $saisies)
		{
			// Switch des bases de données
			mysql_select_db(DB_PREFIX.$sub_company);
			mysql_query('SET NAMES "latin-1"');

			Managing::switchDb($sub_company);

			$synthese = Activis::getNewModel("Synthese.Lines");
			$affectations = Activis::getNewModel("Projet.Projet_Affectations");
			$productions  = Activis::getNewModel("Projet.Projet_Productions");

			$comments = $all_comments[$sub_company];
			$comments_project = $all_comments_project[$sub_company];

			$projet_prestations_array = array_keys($all_projet_prestations_array[$sub_company]);

			// $this->_sendFinishNotification($projet_prestations_array[$sub_company], $sub_company);
			// $this->_sendNewAffectationNotification((int)$this->_request->getParam('prestation_id'), $sub_company);

			foreach($saisies as $affectation_id => $saisie_days)
			{
				foreach($saisie_days as $date_saisie => $heure)
				{
					$date_tmp = $this->_request->getParam('date');
					list($jour, $mois, $annee) =  explode('/', $date_tmp[$sub_company][$affectation_id]);
					$date = (empty($date_saisie) ? date('Y-m-d', mktime(0, 0, 0, $mois, $jour, $annee)) : $date_saisie);

					if(!empty($heure))
					{
						$affectation = $affectations->find($affectation_id)->current();

						$where = $productions->select()
							->where('projet_affectation_id = ?', $affectation->id)
							->where("date = '$date'");

						$production = $productions->fetchAll($where)->current();
						$heure = (float)str_replace(" ","",str_replace(",",".",$heure));

						if(!$production)
						{
							// Création d'une nouvelle ligne
							$production = $productions->fetchNew();
							$production->setFromArray(array(
								'projet_affectation_id' => $affectation_id,
								'date' => $date));
						}
						if (!empty($comments_project[$affectation_id][$date_saisie]))
							$production->comment = utf8_encode($comments_project[$affectation_id][$date_saisie]);
						$production->time += $heure;
						$production->save();
						$modified = true;

						$result = mysql_query("SELECT 
							SUM(projet_production.time) as view_affectation_time_consumed
							FROM projet_production 
							WHERE projet_production.projet_affectation_id = ".$affectation_id);
						$line = mysql_fetch_assoc($result);

						if(!empty($comments[$affectation_id][$date_saisie])) {
							mysql_query("INSERT INTO maintenances_history SET date = '". mysql_escape_string($date) ."',
								projet_affectation_id = $affectation_id, duration = '$heure', user = 24101, 
								comment = '". mysql_real_escape_string($comments[$affectation_id][$date_saisie]) ."'");

						}

						$synthese->saveProduction($production);

						// $affectation->sendExceededNotification();
						$affectation->consumed = $line['view_affectation_time_consumed'];
						$affectation->save();
						$affectation->checkAlerts();

						$projet = $affectation->getProject();
						$projet->checkAlerts();
					}
				}
			}

			/*
			 * Modif Nartex 24/11/2011
			 * Sauvegarde de l'état "tmp_closed", en attendant la clôture officielle
			 */
			// Modif - JBO : clôture de la tâche automatique + clôture de la prestation automatique + passage du projet à 100% si toutes les prestations sont fermées
			mysql_query("UPDATE projet_affectation SET closed = 1 WHERE id IN ('".implode("','", $projet_prestations_array)."')");

			$presta_stmt = mysql_query("SELECT projet_prestations_id FROM projet_affectation WHERE id IN ('".implode("','", $projet_prestations_array)."') GROUP BY projet_prestations_id");
			$prestations = array();
			while ($row = mysql_fetch_assoc($presta_stmt))
			{
				$result = mysql_fetch_row(mysql_query("SELECT IF(SUM(pa.closed) = COUNT(pa.id), 1, 0) as closed_presta,
													   IF(pp.prestation_id IN (109,94,124,125,205,304,350), 1, 0) as maint_presta,
													   IF(pp.previsionnel_fin <= NOW(), 1, IF(SUM(pa.consumed) >= SUM(pa.time), 1, 0)) as finished_maint_presta
													   FROM projet_affectation pa
													   INNER JOIN projet_prestation pp ON pp.id = pa.projet_prestations_id
													   WHERE pa.projet_prestations_id = '".(int)$row['projet_prestations_id']."'
													   GROUP BY pa.projet_prestations_id"));

				if ($result[0] && !$result[1]) // Toutes les affectations sont fermées (hors maintenance non échue), on tag la prestation qui doit être fermée
					$prestations[] = $row['projet_prestations_id'];
				else if ($result[0] && $result[1] && $result[2]) // cas de figure des maintenances échues, on tag pour fermer aussi
					$prestations[] = $row['projet_prestations_id'];
			}

			if (!empty($prestations))
				mysql_query("UPDATE projet_prestation SET closed = 1, reel_fin = '".date('Y-m-d')."' WHERE id IN ('".implode("','", $prestations)."')");

			$projet_stmt = mysql_query("SELECT projet_id FROM projet_prestation WHERE id IN ('".implode("','", $prestations)."') GROUP BY projet_id");
			$projets = array();
			while ($row = mysql_fetch_assoc($projet_stmt))
			{
				$result = mysql_fetch_row(mysql_query("SELECT IF(SUM(closed) = COUNT(id), 1, 0) as closed_projet FROM projet_prestation WHERE projet_id = ".(int)$row['projet_id']." GROUP BY projet_id"));

				if ($result[0]) // Toutes les prestations sont fermées on tag le projet qui doit être à 100%
					$projets[] = $row['projet_id'];
			}

			if (!empty($projets))
				mysql_query("UPDATE projet SET avancement = 100 WHERE id IN ('".implode("','", $projets)."')");
			// End: modif JBO
		}

		/**
		 * Sauvegarde des heures pot commun (dans la base de Mulhouse)
		 */

		// Switch sur la bases de mulhouse
		mysql_select_db(DB_PREFIX.'mulhouse');
		mysql_query('SET NAMES "latin-1"');

		$common_comments = $this->_request->getParam('detail_common');

		foreach($this->_request->getParam('common_saisie') as $id => $data)
		{
			foreach($data['heure'] as $date_saisie => $heure)
			{
				if($heure)
				{
					$date = $data['date'];
					list($jour, $mois, $annee) = explode('/', $date);
					$date = (empty($date_saisie) ? date("Y-m-d", mktime(0, 0, 0, $mois, $jour, $annee)) : $date_saisie);
					if (!empty($common_comments[$id][$date_saisie]) || ((int)$profil === 10) || ((int)$profil === 11)) {
						$modified = true;
						mysql_query("INSERT INTO common_production (user_id, date, common_activite_id, time, comment) VALUES (".$this->_request->getParam('user_id').", '".$date."' , $id, '".str_replace(',', '.', $heure)."', '".mysql_real_escape_string($common_comments[$id][$date_saisie])."')");
					} else {
						$modified = false;
						$this->_flashMessenger->addMessage('Les heures non associables aux projets n\'ont pas &eacute;t&eacute; enregistr&eacute;es. Vous devez laisser un commentaire.');
					}
				}
			}
		}

		$this->_flashMessenger->addMessage(
			($modified) ?
				'Saisie sauvegard&eacute;e' :
				'Aucune saisie n\'a &eacute;t&eacute; enregistr&eacute;e'
		);

		if($modified)
			die(json_encode(array(true, 'Saisie sauvegard&eacute;e')));
		else
			die(json_encode(array(false, 'Aucune saisie n\'a &eacute;t&eacute; enregistr&eacute;e')));
	}

	function excelAction()
	{
		if($user_id = $this->_request->getParam('user_id'))
		    $this->user = Activis::getModel("Resource.Users")->fetchRow("contact_id = ".$user_id);

		include_once dirname(__FILE__).'/../../../base/library/Spreadsheet/Excel/Writer.php'; 
		$xls = new Spreadsheet_Excel_Writer(); 
		$xls->send('saisie_'.$this->user->prenom.'_'.$this->user->nom.'.xls'); 
		$xls->setCustomColor(12, 230, 230, 230); // Header
		$xls->setCustomColor(13, 233, 239, 248); // Stripes

		$formatHeader =& $xls->addFormat(); 
	    $formatHeader->setBold();
	    $formatHeader->setFgColor(12);

	    $sheet =& $xls->addWorksheet("Projets");
	    $sheet->setInputEncoding('UTF-8');
	    $sheet->writeRow(0, 0, array(
	    	'Client', 
	    	'Projet', 
	    	'Prestation',
	    	utf8_decode('Consommé'),
	    	utf8_decode('Affecté'),
	    	'Date butoir'
	    ), $formatHeader);

		$l = 0;
		$lineFormat =& $xls->addFormat();
		$lineFormat->setFgColor(13);

		// Récupère et traite les informations relatives à la saisie et les sauve dans $_affectations
		$this->gestionAction();

		foreach($this->_affectations as $affectation)
		{
			++$l;

			$sheet->writeRow($l, 0, array(
				$affectation['client'],
				$affectation['projet'],
				($affectation['code'] ? '['.$affectation['code'].'] ' : '').$affectation['prestation'],
				$affectation['consumed'],
				$affectation['time'],
				implode('/', array_reverse(explode('-', $affectation['previsionnel_fin'])))
			), ($l%2 != 0 ? $lineFormat : null));
		}

		$xls->close(); 
		die();
	}

	private static function getRecoverablesHours($user_id, $month_start, $month_end, $hours_per_day)
	{
		$recoverable_hours = 0;

		$sql = 'SELECT
				a.id,
				a.type_id,
				a.begin_date,
				a.end_date,
				a.begin_noon,
				a.end_noon,
				a.creation_date
			FROM
				absence a
			WHERE
				a.contact_id = "' . $user_id . '"
				AND a.type_id = "2"
				AND a.begin_date <= "' . $month_end . '"
				AND a.begin_date >= "' . $month_start . '"
		';
		
		$result_absences = mysql_query($sql);
		while ($row = mysql_fetch_assoc($result_absences)) {
			$begin_date = new DateTime($row['begin_date']);
			$end_date = new DateTime($row['end_date']);
			// $nb_days = (int)$end_date->diff($begin_date)->format('%a') + 1; // +1 obligatoire
			$nb_days = (int)self::_dateDiff($end_date, $begin_date) + 1; // +1 obligatoire

			// les demi-journées
			if ($row['begin_noon']) {
				$nb_days -= 0.5;
			}
			if ($row['end_noon']) {
				$nb_days -= 0.5;
			}
			$recoverable_hours += $nb_days * $hours_per_day;
		}

		return $recoverable_hours;
	}

	private static function _dateDiff($dt1, $dt2) 
	{
		$ts1 = $dt1->format('Y-m-d');
		$ts2 = $dt2->format('Y-m-d');
		$diff = abs(strtotime($ts1)-strtotime($ts2));
		$diff/= 3600*24;
		return $diff;
	}

	private static function getAbsenceForDay($user_id, $cur_date_us, $cur_date_us_dash, $ets)
	{
		$absence = array();

		mysql_select_db(DB_PREFIX . $ets);
		mysql_query('SET NAMES "latin-1"');

		$sql = 'SELECT
				a.id,
				a.type_id,
				a.begin_date,
				a.end_date,
				a.begin_noon,
				a.end_noon
			FROM
				absence a
			WHERE
				a.contact_id = "' . $user_id . '"
				AND a.begin_date <= "' . $cur_date_us . '"
				AND a.end_date >= "' . $cur_date_us . '"
				LIMIT 2
		';
		$result_absences = mysql_query($sql);
		
		if ($row = mysql_fetch_assoc($result_absences)) {
			$half_day = false;
			$absence_count = mysql_num_rows($result_absences);
			// est-ce une demi-journée et la seule de la journée
			if ($row['begin_date'] === $cur_date_us_dash && (int)$row['begin_noon'] === 1 && $absence_count < 2) {
				$half_day = true;
			}
			if ($row['end_date'] === $cur_date_us_dash && (int)$row['end_noon'] === 1 && $absence_count < 2) {
				$half_day = true;	
			}

			$type_id = (int)$row['type_id'];
			$type = 'AUTRE';
			if ($type_id === 1)
				$type = 'CP';
			else if ($type_id === 2)
				$type = 'RECUP';

			$absence['half_day'] = $half_day;
			$absence['type'] = $type;
		}

		return $absence;
	}

	public static function getRessourceProduction($user_id, $month, $year)
	{
		$production = array();

	    $sub_companies = explode(',', SUB_COMPANIES);
	    foreach ($sub_companies as $sub_company)
	    {
	    	$projets = array();

	    	mysql_select_db(DB_PREFIX . $sub_company);
	    	mysql_query('SET NAMES "latin-1"');

	    	$ets = ucfirst($sub_company);

			$projets_stmt = mysql_query('SELECT e.raison_sociale as client, p.nom as projet, pp.name as prestation, pp.id
	 									 FROM projet_affectation pa
	 									 INNER JOIN projet_prestation pp ON pa.projet_prestations_id = pp.id
	 									 INNER JOIN projet p ON pp.projet_id = p.id
	 									 INNER JOIN am_crm_entreprises e ON e.contact_id = p.client_id
	 									 WHERE pa.user_id = "'.$user_id.'"
										 GROUP BY pp.id');

			while ($row = mysql_fetch_assoc($projets_stmt)) {
				$projets[$row['id']] = $row;
			}

		    // Prestations
		    $presta_stmt = mysql_query('SELECT pp.time as time, pp.comment as comment, pp.date as date, pa.projet_prestations_id, "'.$ets.'" as ets
										FROM projet_production pp
										INNER JOIN projet_affectation pa ON pp.projet_affectation_id = pa.id
										WHERE pa.user_id = "'.$user_id.'"
										AND EXTRACT(YEAR FROM pp.date) = "'.$year.'" AND EXTRACT(MONTH FROM pp.date) = "'.$month.'"
										ORDER BY pp.date');

		    while ($row = mysql_fetch_assoc($presta_stmt)) {
			    $row['comment'] = utf8_decode($row['comment']);
			    $production[$row['date']][] = array_merge($row, $projets[$row['projet_prestations_id']]);
		    }

			// Prestations
			/*$presta_stmt = mysql_query('SELECT pp.time as time, pp.comment as comment, pp.date as date, pa.projet_prestations_id, "'.$ets.'" as ets
										FROM projet_production pp
										INNER JOIN projet_affectation pa ON pp.projet_affectation_id = pa.id
										LEFT JOIN maintenances_history mh ON pp.projet_affectation_id = mh.projet_affectation_id
										WHERE pa.user_id = "'.$user_id.'"
										AND EXTRACT(YEAR FROM pp.date) = "'.$year.'" AND EXTRACT(MONTH FROM pp.date) = "'.$month.'"
										AND mh.id IS NULL
										ORDER BY pp.date');

			while ($row = mysql_fetch_assoc($presta_stmt)) {
				$row['comment'] = utf8_decode($row['comment']);
				$production[$row['date']][] = array_merge($row, $projets[$row['projet_prestations_id']]);
			}
			// Maintenance
			$maint_stmt = mysql_query('SELECT mh.date as date, mh.duration as time, mh.comment as comment, pa.projet_prestations_id, "'.$ets.'" as ets
									   FROM maintenances_history mh
									   INNER JOIN projet_affectation pa ON pa.id= mh.projet_affectation_id 
									   WHERE pa.user_id = "'.$user_id.'"
									   AND EXTRACT(YEAR FROM mh.date) = "'.$year.'" AND EXTRACT(MONTH FROM mh.date) = "'.$month.'"
									   ORDER BY mh.date');

			while ($row = mysql_fetch_assoc($maint_stmt)) {
				$production[$row['date']][] = array_merge($row, $projets[$row['projet_prestations_id']]);
			}*/
			// Gestion/commercial/évènementiel
			/*$gestion_stmt = mysql_query('SELECT cp.date as date, cp.time as time, cp.comment as comment, ca.name as prestation, common_activite_id as type_id, "Groupe" as ets
										 FROM common_production cp
										 INNER JOIN common_activite ca ON ca.id = cp.common_activite_id
										 WHERE cp.user_id = "'.$user_id.'"
										 AND EXTRACT(YEAR FROM cp.date) = "'.$year.'" AND EXTRACT(MONTH FROM cp.date) = "'.$month.'"
										 ORDER BY cp.date');

			while ($row = mysql_fetch_assoc($gestion_stmt)) {
				$production[$row['date']][] = $row;
			}*/
		} // foreach($sub_companies as $sub_company)

		ksort($production);

		return $production;
	}

	/**
	 * Export des heures récupérables dans un fichier Excel (*.xls)
	 */
	function exportrecoverablehoursAction()
	{
		if ($user_id = $this->_request->getParam('user_id'))
		    $this->user = Activis::getModel("Resource.Users")->fetchRow("contact_id = ".$user_id);

		$year = (int)date('Y', strtotime(date('Y-m').'-01 -3 months'));
		$month = (int)date('n', strtotime(date('Y-m').'-01 -3 months')); // month number without leading zero
		$heures_theoriques_par_jour = 7.00;
		$result = mysql_query('SELECT heures_jour FROM am_crm_salaries WHERE contact_id = "'. $user_id .'"');
		$row = mysql_fetch_assoc($result);
		if ($row !== false) {
			$heures_theoriques_par_jour = (float)$row['heures_jour']; // TODO
		}

    	// Permet de vérifier l'établissement de l'utilisateur
    	$ADFile = file('http://srvinfra/ADGroups/');
    	$ADusers = new SimpleXMLElement("<users>".$ADFile[0]."</users>");

    	foreach($ADusers as $ADuser)
    	{
    		$ets = strtolower(trim($ADuser->etablissement));
    		
    		if (strtolower($this->user->sam) == strtolower($ADuser['sam']))
	    		break;
		}

		/*$bank_holidays_tmp1 = array_keys(Managing_DateTime::getFrenchBankHolidays($year, ($ets !== 'mulhouse')));
		$bank_holidays_tmp2 = array_keys(Managing_DateTime::getFrenchBankHolidays($year - 1, ($ets !== 'mulhouse')));
		$bank_holidays = array_merge($bank_holidays_tmp1, $bank_holidays_tmp2);*/
		$bank_holidays = array(); // Suppression du calcul auto des jours fériés => créés comme des absences

		// store line number with SUM operations, for "Synthèse" worksheet
		$total_line_index = array();

		
		// $m_plus1_month_start = date('Y-m', strtotime('+1 months')) . '-01';
		// $m_plus1_month_end   = date('Y-m', strtotime('+1 months')) . '-' . date('t', strtotime('+1 months'));
		// $m_plus2_month_start = date('Y-m', strtotime('+2 months')) . '-01';
		// $m_plus2_month_end   = date('Y-m', strtotime('+2 months')) . '-' . date('t', strtotime('+2 months'));
		// $m_plus3_month_start = date('Y-m', strtotime('+3 months')) . '-01';
		// $m_plus3_month_end   = date('Y-m', strtotime('+3 months')) . '-' . date('t', strtotime('+3 months'));

		//
		// gestion des heures déjà récupérés (mois en cours, m-1, m-2 et m-3)
		//
		/*$m0_month_start = date('Y-m') . '-01';
		$m0_month_end   = date('Y-m-d');
		$m1_month_start = date('Y-m', strtotime('-1 months')) . '-01';
		$m1_month_end   = date('Y-m', strtotime('-1 months')) . '-' . date('t', strtotime('-1 months'));
		$m2_month_start = date('Y-m', strtotime('-2 months')) . '-01';
		$m2_month_end   = date('Y-m', strtotime('-2 months')) . '-' . date('t', strtotime('-2 months'));
		$m3_month_start = date('Y-m', strtotime('-3 months')) . '-01';
		$m3_month_end   = date('Y-m', strtotime('-3 months')) . '-' . date('t', strtotime('-3 months'));
		$m4_month_start = date('Y-m', strtotime('-4 months')) . '-01';
		$m4_month_end   = date('Y-m', strtotime('-4 months')) . '-' . date('t', strtotime('-4 months'));

		$m_plus1_recoverable_hours = self::getRecoverablesHours($user_id, $m_plus1_month_start, $m_plus1_month_end, $heures_theoriques_par_jour);
		$m_plus2_recoverable_hours = self::getRecoverablesHours($user_id, $m_plus2_month_start, $m_plus2_month_end, $heures_theoriques_par_jour);
		$m_plus3_recoverable_hours = self::getRecoverablesHours($user_id, $m_plus3_month_start, $m_plus3_month_end, $heures_theoriques_par_jour);
		
		$m0_recoverable_hours = self::getRecoverablesHours($user_id, $m0_month_start, $m0_month_end, $heures_theoriques_par_jour);
		$m1_recoverable_hours = self::getRecoverablesHours($user_id, $m1_month_start, $m1_month_end, $heures_theoriques_par_jour);
		$m2_recoverable_hours = self::getRecoverablesHours($user_id, $m2_month_start, $m2_month_end, $heures_theoriques_par_jour);
		$m3_recoverable_hours = self::getRecoverablesHours($user_id, $m3_month_start, $m3_month_end, $heures_theoriques_par_jour);
		$m4_recoverable_hours = self::getRecoverablesHours($user_id, $m4_month_start, $m4_month_end, $heures_theoriques_par_jour);

		$recoverable_hours = $m0_recoverable_hours + $m1_recoverable_hours + $m2_recoverable_hours + $m3_recoverable_hours;*/

		$m4_total_heures_travailles = 0;
		$m4_total_heures_travaillables = 0;

	    $m4_year = (int)date('Y', strtotime(date('Y-m').'-01 -4 months'));
	    $m4_month = (int)date('n', strtotime(date('Y-m').'-01 -4 months')); // month number without leading zero

		$production = self::getRessourceProduction($user_id, $m4_month, $m4_year);
		
		$days_in_month = cal_days_in_month(CAL_GREGORIAN, $m4_month, $m4_year);
		$max_displayed_days = $days_in_month;
		$dates = array();
		for ($day = 1; $day <= $max_displayed_days; $day++) {
			$dates[] = $m4_year . '-' . str_pad($m4_month, 2, '0', STR_PAD_LEFT) . '-' . str_pad($day, 2, '0', STR_PAD_LEFT);
		}

		foreach ($dates as $date) {

			$lines = $production[$date];
			if (empty($lines)) {
				// aucune saisie pour ce jour
				$cur_date = $date;
				if (self::isWeekend($date))
					continue; // go to next day
				
			} else {
				foreach ($lines as $line) {
					$cur_date = $line['date'];
					$m4_total_heures_travailles += $line['time'];
				}
			}

			// vérifie si la personne était absente : journée pleine ou demi-journée
			$cur_date_us_dash = implode('-', array_reverse(explode('/', $cur_date)));
			$cur_date_us = str_replace('-', '/', $cur_date_us_dash);
			$absence = self::getAbsenceForDay($user_id , $cur_date_us, $cur_date_us_dash, $ets);

			// calcul des heures travaillables
			$heures_travaillables = $heures_theoriques_par_jour;

			// RAZ heures travaillables pour les week-end
			if (self::isWeekend($cur_date)) {
				$heures_travaillables = 0;
			}
			// RAZ heures travaillables pour les jours fériés
			if (in_array($cur_date_us, $bank_holidays)) {
				$heures_travaillables = 0;
			}
			
			if (!empty($absence)) {
				// MAJ heures travaillables pour les absences suivantes : CP, RECUP et AUTRE
				if ($absence['half_day']) {
					$heures_travaillables /= 2;	
				} else {
					$heures_travaillables = 0;
				}
			}

			$m4_total_heures_travaillables += $heures_travaillables;
		}

		$m4_heures_recup_utilisables = $m4_total_heures_travailles - $m4_total_heures_travaillables;


		// pour tester m-4			
		// var_dump($m4_total_heures_travailles);
		// var_dump($m4_total_heures_travaillables);
		// var_dump($m4_heures_recup_utilisables);
		// var_dump($m4_recoverable_hours);
		// $m4_recoverable_hours = 14;
		// exit;

		include_once dirname(__FILE__).'/../../../base/library/Spreadsheet/Excel/Writer.php'; 
		$xls = new Spreadsheet_Excel_Writer(); 

		$filename = 'recup_'.date('Y').'-'.date('m').'-'.date('d').'_'.$this->user->prenom.'_'.$this->user->nom.'.xls';
		
		$xls->send($filename); 
		$xls->setCustomColor(12, 230, 230, 230); // Header
		$xls->setCustomColor(13, 197, 217, 241); // Stripes

		$formatHeader =& $xls->addFormat(); 
	    $formatHeader->setBold();
	    $formatHeader->setFgColor(12);

		$lineBlueBold =& $xls->addFormat();
		$lineBlueBold->setBold();
		$lineBlueBold->setFgColor(13);

		$xls->setCustomColor(44, 255, 204, 0); // orange
		$lineOrange =& $xls->addFormat();
		$lineOrange->setFgColor(44);

		$lineFormat =& $xls->addFormat();
		$lineFormat->setFgColor(13);

		$lineFormat_alt =& $xls->addFormat();
		$lineFormat_alt->setFgColor('white');

		$lineFormat_title =& $xls->addFormat();
		$lineFormat_title->setFgColor(13);
		$lineFormat_title->setTop(1);
		$lineFormat_title->setTopColor('grey');

		$lineFormat_alt_title =& $xls->addFormat();
		$lineFormat_alt_title->setFgColor('white');
		$lineFormat_alt_title->setTop(1);
		$lineFormat_alt_title->setTopColor('grey');


		// Noms des feuilles du fichier Excel
	    $worksheets = array(
	    	'M-3',
	    	'M-2',
	    	'M-1',
	    	'En cours',
	    	utf8_decode('Synthèse')
	    );

	    foreach ($worksheets as $worksheet_name) {

		    $sheet =& $xls->addWorksheet($worksheet_name);
		    $sheet->setInputEncoding('UTF-8');

		    if ($worksheet_name == utf8_decode('Synthèse'))
		    {
		    	$l = 0;
			    $sheet->writeRow($l++, 0, array(
			    	$this->user->prenom.'_'.$this->user->nom,
			    	null
			    ), $formatHeader);
			    $sheet->writeRow($l++, 0, array(
			    	'Mois',
			    	utf8_decode('Solde heures récupérables')
			    ), $lineBlueBold);
			    $sheet->writeRow($l++, 0, array(
					'En cours',
					"='En cours'!K".$total_line_index['En cours']
					
				), null);
				$sheet->writeRow($l++, 0, array(
					'M-1',
					"='M-1'!K".$total_line_index['M-1']
				), null);
				$sheet->writeRow($l++, 0, array(
					'M-2',
					"='M-2'!K".$total_line_index['M-2']
				), null);
				$sheet->writeRow($l++, 0, array(
					'M-3',
					"='M-3'!K".$total_line_index['M-3']
				), null);

				$l++;
				$sheet->writeRow($l++, 0, array(
					'Solde restant au : ' . date('d/m/Y'),
					'=SUM(B3:B6)'
				), $lineBlueBold);
		    }
		    else
		    {
		    	$headers = array(
		    		'Jour',
			    	'Date',
			    	'Client', 
			    	'Projet', 
			    	'Prestation',
			    	'Heures',
			    	'Commentaire',
			    	'Etablissement',
			    	utf8_decode('Heures réelles'),
			    	utf8_decode('Heures théoriques')
			    );

			    $sheet->writeRow(0, 0, $headers, $formatHeader);
				
				$heures_recup_utilisees = 0;
				$heures_recup_utilisables = 0;
				$total_heures_travailles = 0;
				$total_heures_travaillables = 0;
			    
				$production = self::getRessourceProduction($user_id, $month, $year);
				
				$days_in_month = cal_days_in_month(CAL_GREGORIAN, $month, $year);
				$max_displayed_days = $days_in_month;
				if ($worksheet_name === 'En cours') {
					// display data until today
					$max_displayed_days = (int)date('d');
				}

				$dates = array();
				for ($day = 1; $day <= $max_displayed_days; $day++) {
					$dates[] = $year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT) . '-' . str_pad($day, 2, '0', STR_PAD_LEFT);
				}


				$l = 0;
				$cur_date = null;
				$group_date = true;
				$first_line = true;
				$days_count = 0;
				$working_days = 0;

				foreach ($dates as $date) {

					$heures_travaillables = $heures_theoriques_par_jour;

					$lines = $production[$date];
					// s'il n'y a aucune saisie pour ce jour, on le rajoute à la liste
					if (empty($lines)) {

						if (self::isWeekend($date))
							continue; // go to next day

						if ($date == date('Y-m-d'))
							continue; // go to next day

						++$l;

						$day_date = $first_line ? implode('/', array_reverse(explode('-', $date))) : '';
						$day_name = $first_line ? date('l', strtotime($date)) : '';
						$day_name = $first_line ? $this->_days_list[$day_name] : '';

						$data = array(
							$day_name,
							$day_date,
							null,
							null,
							null,
							null,
							null,
							null,
							null,
							null
						);
						$sheet->writeRow($l, 0, $data, ($group_date ? $lineFormat : $lineFormat_alt));

						$cur_date = $date;
						$first_line = false;
						++$days_count;
					}
					foreach ($lines as $line) {
						if (isset($line['type_id']) && $line['type_id'] == 5)
							$heures_recup_utilisees += abs($line['time']);
	
						++$l;

						$day_date = $first_line ? implode('/', array_reverse(explode('-', $line['date']))) : '';
						$day_name = $first_line ? date('l', strtotime($line['date'])) : '';
						$day_name = $first_line ? $this->_days_list[$day_name] : '';
					
						$data = array(
							$day_name,
							$day_date,
							(isset($line['client']) ? $line['client'] : ''),
							(isset($line['projet']) ? $line['projet'] : ''),
							(isset($line['prestation']) ? $line['prestation'] : ''),
							$line['time'],
							(isset($line['comment']) ? $line['comment'] : ''),
							$line['ets'],
							null,
							null
						);

						$sheet->writeRow($l, 0, $data, ($group_date ? $lineFormat : $lineFormat_alt));

						$cur_date = $line['date'];
						$first_line = false;
						++$days_count;

						$total_heures_travailles += $line['time'];
					}

					//
					//  affiche le total pour la journée
					//
					$cur_date_us_dash = implode('-', array_reverse(explode('/', $cur_date)));
					$cur_date_us = str_replace('-', '/', $cur_date_us_dash);

					//
					// gestion des absences
					//
					$absence = self::getAbsenceForDay($user_id , $cur_date_us, $cur_date_us_dash, $ets);

					// RAZ heures travaillables pour les week-end
					if (self::isWeekend($cur_date)) {
						$heures_travaillables = 0;
					}
					// RAZ heures travaillables pour les jours fériés
					if (in_array($cur_date_us, $bank_holidays)) {
						$heures_travaillables = 0;
					}
					

					if (!empty($absence)) {
						// MAJ heures travaillables pour les absences suivantes : CP, RECUP et AUTRE
						if ($absence['half_day']) {
							$heures_travaillables /= 2;	
						} else {
							$heures_travaillables = 0;
						}
					}

					++$l;

					$data = array(
						null,
						null,
						'TOTAL',
						null,
						null,
						null,
						null,
						null,
						'=ROUND(SUM(F'.($l + 1 - $days_count).':F'.$l.'),2)',
						$heures_travaillables
					);
					$sheet->writeRow($l, 0, $data, ($group_date ? $lineFormat_title : $lineFormat_alt_title));

					$total_heures_travaillables += $heures_travaillables;

					$group_date = !$group_date;
					$first_line = true;
					$days_count = 0; // Ne pas compter le header
					++$working_days;
				}

				$heures_recup_utilisables = $total_heures_travailles - $total_heures_travaillables;
				// if ($worksheet_name === 'M-3') {
				// 	$m4_solde = $m4_heures_recup_utilisables /*- $m4_recoverable_hours*/;
				// 	if ($m4_solde > 0) {
				// 		$m4_solde = 0;
				// 	} else {
				// 		// additionne le solde M-4 si négatif
				// 		$heures_recup_utilisables += $m4_solde;
				// 	}
				// }

				// if ($worksheet_name === 'M-2') {
				// 	$recoverable_hours += $m_plus1_recoverable_hours;
				// } else if ($worksheet_name === 'M-1') {
				// 	$recoverable_hours += $m_plus2_recoverable_hours;
				// } else if ($worksheet_name === 'En cours') {
				// 	$recoverable_hours += $m_plus3_recoverable_hours;
				// }

				// if ($heures_recup_utilisables > 0) {
				// 	if ($recoverable_hours <= $heures_recup_utilisables)
				// 		$heures_recup_utilisees = $recoverable_hours;
				// 	else
				// 		$heures_recup_utilisees = $heures_recup_utilisables;
				// }

				// $heures_recup_utilisees = 0;

				$l+=2;

				if ($worksheet_name === 'M-3') {
					$data = array(
						null,
						null,
						utf8_decode('SOLDE MOIS PRÉCÉDENT (si négatif)'),
						null,
						null,
						null,
						null,
						null,
						null,
						null,
						$m4_solde
					);
					$sheet->writeRow($l, 0, $data, null);
					$l++;
				}

				$data = array(
					null,
					null,
					utf8_decode('TOTAL HEURES RÉCUPÉRÉES'),
					null,
					null,
					null,
					null,
					null,
					$heures_recup_utilisees
				);
				$sheet->writeRow($l, 0, $data, null);

				$recoverable_hours -= $heures_recup_utilisees;


				$l++;
				$data = array(
					null,
					null,
					'TOTAL MENSUEL',
					null,
					null,
					null,
					null,
					null,
					utf8_decode('RÉEL'),      // heures travaillées
					utf8_decode('THÉORIQUE'), // heures travaillables
					utf8_decode('RÉCUPÉRABLES')      // heures récupérables
				);
				$sheet->writeRow($l, 0, $data, $lineOrange);
				$total_line_index[$worksheet_name] = $l + 2;

				$l++;
				$tmp = '=ROUND(I'.($l + 1).'-J'.($l + 1);
				if ($worksheet_name === 'M-3') {
					$tmp .= '+K'.($l - 2);
				}
				$tmp .= ',2)';
				$data = array(
					null,
					null,
					null,
					null,
					null,
					null,
					null,
					null,
					// '=ROUND(SUM(I2:I'.($l - 2).')-I'.($l-1).',2)',       // heures travaillées
					'=ROUND(SUM(I2:I'.($l - 2).'),2)',       // heures travaillées
					'=ROUND(SUM(J2:J'.($l - 2).'),2)',       // heures travaillables
					$tmp // heures récupérables
				);
				$sheet->writeRow($l, 0, $data, $lineOrange);

		    	if ($month < 12) {
		    		$month++;
		    	} else {
		    		$year++;
		    		$month = 1;
		    	}

		    } // if ($worksheet_name != utf8_decode('Synthèse'))

		} // foreach $worksheets

		$xls->close(); 
		exit;
	}

	function excelmensuelAction()
	{
		if ($user_id = $this->_request->getParam('user_id'))
		    $this->user = Activis::getModel("Resource.Users")->fetchRow("contact_id = ".$user_id);

		$year = (int)$this->_request->getParam('annee');
		$month = (int)$this->_request->getParam('mois');


		include_once dirname(__FILE__).'/../../../base/library/Spreadsheet/Excel/Writer.php'; 
		$xls = new Spreadsheet_Excel_Writer(); 

		$filename = 'saisie_'.$year.'-'.str_pad($month, 2, '0', STR_PAD_LEFT).'_'.$this->user->prenom.'_'.$this->user->nom.'.xls';
		
		$xls->send($filename); 
		$xls->setCustomColor(12, 230, 230, 230); // Header
		$xls->setCustomColor(13, 197, 217, 241); // Stripes

		$formatHeader =& $xls->addFormat(); 
	    $formatHeader->setBold();
	    $formatHeader->setFgColor(12);

		$lineBlueBold =& $xls->addFormat();
		$lineBlueBold->setBold();
		$lineBlueBold->setFgColor(13);

		$lineFormat =& $xls->addFormat();
		$lineFormat->setFgColor(13);

		$lineFormat_alt =& $xls->addFormat();
		$lineFormat_alt->setFgColor('white');

		$lineFormat_title =& $xls->addFormat();
		$lineFormat_title->setFgColor(13);
		$lineFormat_title->setTop(1);
		$lineFormat_title->setTopColor('grey');

		$lineFormat_alt_title =& $xls->addFormat();
		$lineFormat_alt_title->setFgColor('white');
		$lineFormat_alt_title->setTop(1);
		$lineFormat_alt_title->setTopColor('grey');

	    $sheet =& $xls->addWorksheet('Projets');
	    $sheet->setInputEncoding('UTF-8');

    	$headers = array(
    		'Jour',
	    	'Date',
	    	'Client', 
	    	'Projet', 
	    	'Prestation',
	    	'Heures',
	    	'Commentaire',
	    	'Etablissement',
	    	utf8_decode('Heures travaillées')
	    );

	    $sheet->writeRow(0, 0, $headers, $formatHeader);
		
	    $i = 0;
	    $production = array();
	    $sub_companies = explode(',', SUB_COMPANIES);
	   	
	    foreach($sub_companies as $sub_company)
	    {
	    	$projets = array();
	    	// Switch des bases de données
	    	mysql_select_db(DB_PREFIX . $sub_company);
	    	mysql_query('SET NAMES "latin-1"');

	    	$ets = ucfirst($sub_company);

			$projets_stmt = mysql_query('SELECT e.raison_sociale as client, p.nom as projet, pp.name as prestation, pp.id
	 									 FROM projet_affectation pa
	 									 INNER JOIN projet_prestation pp ON pa.projet_prestations_id = pp.id
	 									 INNER JOIN projet p ON pp.projet_id = p.id
	 									 INNER JOIN am_crm_entreprises e ON e.contact_id = p.client_id
	 									 WHERE pa.user_id = "'.$user_id.'"
										 GROUP BY pp.id');

			while ($row = mysql_fetch_assoc($projets_stmt))
				$projets[$row['id']] = $row;

			// Prestations
			$presta_stmt = mysql_query('SELECT pp.time as time, pp.date as date, pa.projet_prestations_id, "'.$ets.'" as ets
										FROM projet_production pp
										INNER JOIN projet_affectation pa ON pp.projet_affectation_id = pa.id
										LEFT JOIN maintenances_history mh ON pp.projet_affectation_id = mh.projet_affectation_id
										WHERE pa.user_id = "'.$user_id.'"
										AND EXTRACT(YEAR FROM pp.date) = "'.$year.'" AND EXTRACT(MONTH FROM pp.date) = "'.$month.'"
										AND mh.id IS NULL
										ORDER BY pp.date');

			while ($row = mysql_fetch_assoc($presta_stmt))
				$production[$row['date'].'-'.++$i] = array_merge($row, $projets[$row['projet_prestations_id']]);

			// Maintenance
			$maint_stmt = mysql_query('SELECT mh.date as date, mh.duration as time, mh.comment as comment, pa.projet_prestations_id, "'.$ets.'" as ets
									   FROM maintenances_history mh
									   INNER JOIN projet_affectation pa ON pa.id= mh.projet_affectation_id 
									   WHERE pa.user_id = "'.$user_id.'"
									   AND EXTRACT(YEAR FROM mh.date) = "'.$year.'" AND EXTRACT(MONTH FROM mh.date) = "'.$month.'"
									   ORDER BY mh.date');

			while ($row = mysql_fetch_assoc($maint_stmt))
				$production[$row['date'].'-'.++$i] = array_merge($row, $projets[$row['projet_prestations_id']]);

			// Gestion/commercial/évènementiel
			$gestion_stmt = mysql_query('SELECT cp.date as date, cp.time as time, cp.comment as comment, ca.name as prestation, "Groupe" as ets
										 FROM common_production cp
										 INNER JOIN common_activite ca ON ca.id = cp.common_activite_id
										 WHERE cp.user_id = "'.$user_id.'"
										 AND EXTRACT(YEAR FROM cp.date) = "'.$year.'" AND EXTRACT(MONTH FROM cp.date) = "'.$month.'"
										 ORDER BY cp.date');

			while ($row = mysql_fetch_assoc($gestion_stmt))
				$production[$row['date'].'-'.++$i] = $row;
		}

		ksort($production);
		
		$l = 0;
		$cur_date = null;
		$group_date = true;
		$first_line = true;
		$days_count = 0;
		$working_days = 0;

		// pour chaque saisie concernant ce mois de production
		foreach($production as $ligne_prod)
		{
			if (!is_null($cur_date) && $cur_date != $ligne_prod['date'])
			{
				++$l;
				$data = array(
					null,
					null,
					'TOTAL',
					null,
					null,
					null,
					null,
					null,
					'=SUM(F'.($l + 1 - $days_count).':F'.$l.')'
				);
				$sheet->writeRow($l, 0, $data, ($group_date ? $lineFormat_title : $lineFormat_alt_title));

				$group_date = !$group_date;
				$first_line = true;
				$days_count = 0; // Ne pas compter le header
				++$working_days;
			}

			++$l;
			$date = $first_line ? implode('/', array_reverse(explode('-', $ligne_prod['date']))) : '';

			$day_name = $first_line ? date('l', strtotime($ligne_prod['date'])) : '';
			$day_name = $first_line ? $this->_days_list[$day_name] : '';
			$data = array(
				$day_name,
				$date,
				(isset($ligne_prod['client']) ? $ligne_prod['client'] : ''),
				(isset($ligne_prod['projet']) ? $ligne_prod['projet'] : ''),
				(isset($ligne_prod['prestation']) ? $ligne_prod['prestation'] : ''),
				$ligne_prod['time'],
				(isset($ligne_prod['comment']) ? $ligne_prod['comment'] : ''),
				$ligne_prod['ets'],
				null
			);
			$sheet->writeRow($l, 0, $data, ($group_date ? $lineFormat : $lineFormat_alt));

			$cur_date = $ligne_prod['date'];
			$first_line = false;
			++$days_count;
		}

		++$l;
		$data = array(
			null,
			null,
			'TOTAL',
			null,
			null,
			null,
			null,
			null,
			'=SUM(F'.($l + 1 - $days_count).':F'.$l.')'
		);
		$sheet->writeRow($l, 0, $data, ($group_date ? $lineFormat_title : $lineFormat_alt_title));
		++$working_days;

		$l+=2;
		$data = array(
			null,
			null,
			'TOTAL MENSUEL',
			null,
			null,
			null,
			null,
			null,
			'REEL' // heures travaillées
		);
		$sheet->writeRow($l, 0, $data, null);

		$l++;
		$data = array(
			null,
			null,
			null,
			null,
			null,
			null,
			null,
			null,
			'=SUM(I2:I'.($l - 2).')' // heures travaillées
		);
		$sheet->writeRow($l, 0, $data, null);

		// Calcul auto des heures travaillees théoriques + récup - Uniquement certains utilisateurs, le calcul pouvant varier selon la saisie
		if (Activis::registry('user')->contact_id == '59065' // JBO
			|| Activis::registry('user')->contact_id == '57095' // LLA
			|| Activis::registry('user')->contact_id == '56406') // CFR
		{
			++$l;
			$sheet->writeRow(++$l, 0, array(
				null,
				'JOURS TRAVAILLES',
				null,
				null,
				null,
				null,
				null,
				$working_days
			), null);

			$sheet->writeRow(++$l, 0, array(
				null,
				'HEURES TRAVAILLES THEORIQUES',
				null,
				null,
				null,
				null,
				null,
				($working_days * 7)
			), null);

			$sheet->writeRow(++$l, 0, array(
				null,
				'HEURES DE RECUP',
				null,
				null,
				null,
				null,
				null,
				'=H'.($l-2).'-'.($working_days * 7)
			), null);
		}

		$xls->close(); 
		exit;
	}

	function ajaxgethoursAction()
	{
		$date = implode('-', array_reverse(explode('/', $this->_request->getParam('date'))));
	}

	public function ajaxgetplannedhoursmodalAction()
	{
		$response = $this->getResponse();

		$affectationId = intval($this->_request->getParam('affectationId'));
		$year = intval($this->_request->getParam('year'));
		$month = intval($this->_request->getParam('month'));
		$day = intval($this->_request->getParam('day'));

		$beginDate = new DateTime($year.'-'.$month.'-'.$day);

		//Récupération de tous les plannings à cette date & toutes les planifications sans fin concernant ce jour
		$plannings = Managing_DailyPlanning::fetchByDateAndAffectation($beginDate, $affectationId);
		if(is_a($plannings, '\Exception')) $plannings = array();

		foreach($plannings as $planning){
			$planning->setExceptions(Managing_DailyPlanningException::fetchByPlanningId($planning->getId()));
		}

		//Construction du tableau de blocs concernés par le jour cliqué
		$blocPlannings = array();

		$creating = 0;
		if(empty($plannings)){
			$planning = new Managing_DailyPlanning(0, $affectationId, $beginDate);
			$planning->setEndDate($beginDate);
			array_push($plannings, $planning);
			if(empty($blocPlannings))
				$creating = 1;
		}

		//Envoi à la vue
		$this->view->assign('planningsInformation', array('fields' => $plannings, 'blocs' => $blocPlannings));
		$this->view->assign('creating', $creating);
		$this->view->assign('dailyPlanningInformation', array(
			'TYPE_DAILY' => Managing_DailyPlanning::TYPE_DAILY,
			'TYPE_WEEKLY' => Managing_DailyPlanning::TYPE_WEEKLY,
			'TYPE_MONTHLY' => Managing_DailyPlanning::TYPE_MONTHLY,
			'MONDAY_WEIGHT' => Managing_DailyPlanning::MONDAY_WEIGHT,
			'TUESDAY_WEIGHT' => Managing_DailyPlanning::TUESDAY_WEIGHT,
			'WEDNESDAY_WEIGHT' => Managing_DailyPlanning::WEDNESDAY_WEIGHT,
			'THURSDAY_WEIGHT' => Managing_DailyPlanning::THURSDAY_WEIGHT,
			'FRIDAY_WEIGHT' => Managing_DailyPlanning::FRIDAY_WEIGHT,
			'EVERY_X_OF_Y_MONTH' => Managing_DailyPlanning::EVERY_X_OF_Y_MONTH,
			'EVERY_TYPE_OF_DAY_OF_MONTH' => Managing_DailyPlanning::EVERY_TYPE_OF_DAY_OF_MONTH,
		));

		$this->render('modalplannedhours', 'modalplannedhours');

		$result = $response->getBody('modalplannedhours');
		die($result);
	}

	public function ajaxsavedailyhoursAction()
	{
		$db = new PDO('mysql:dbname='.DB_NAME.';host:localhost', DB_USER_NAME, DB_PASSWORD);
		try {
			$db->beginTransaction();
			if (empty($_POST['planning'])) {
				die(json_encode(array(true, 'Erreur lors de l\'envoi des données')));
			}

			//Récupération des données :
			$postData = $_POST['planning'];

			$dailyPlannings = Managing_DailyPlanning::purify($postData);

			//Parcourt de chaque planning afin de savoir si il faut l'update ou le créer
			$planningsToInsert = array();
			foreach($dailyPlannings as $planning){
				if($planning->getId() != 0){
					//Si le planning contient 0 heures
					if($planning->getAffectedTime() == 0){
						//On supprime
						$deletePlanning = Managing_DailyPlanning::delete($planning->getId(), $db);
						if(is_a($deletePlanning, '\Exception')){
							throw new \Exception($deletePlanning->getMessage(), $deletePlanning->getCode(), $deletePlanning->getPrevious());
						}
					}
					else{
						$hydratePlanning = Managing_DailyPlanning::hydrate($planning, $db);
						if(is_a($hydratePlanning, '\Exception')){
							throw new \Exception($hydratePlanning->getMessage(), $hydratePlanning->getCode(), $hydratePlanning->getPrevious());
						}

						$exceptions = $planning->getExceptions();
						$oldExceptions = Managing_DailyPlanningException::fetchByPlanningId($planning->getId(), $db);

						$exceptionsDifference = Managing_DailyPlanningException::diffBetweenTwo($exceptions, $oldExceptions);

						if(!is_a($exceptionsDifference, '\Exception')){
							if(!empty($exceptionsDifference['toAdd'])){
								$addExceptions = Managing_DailyPlanningException::insertMultiple($exceptionsDifference['toAdd'], $db);
								if(is_a($addExceptions, '\Exception'))
									throw new \Exception($addExceptions->getMessage(), $addExceptions->getCode(), $addExceptions->getPrevious());
							}
							
							if(!empty($exceptionsDifference['toRemove'])){
								$idsToRemove = array();
								foreach($exceptionsDifference['toRemove'] as $exception){
									$idsToRemove[] = $exception->getId();
								}
								$removeExceptions = Managing_DailyPlanningException::deleteMultiple($idsToRemove, $db);
								if(is_a($removeExceptions, '\Exception'))
									throw new \Exception($removeExceptions->getMessage(), $removeExceptions->getCode(), $removeExceptions->getPrevious());
							}

							if(!empty($exceptionsDifference['toHydrate'])){
								foreach($exceptionsDifference['toHydrate'] as $exception){
									$hydrateException = Managing_DailyPlanningException::hydrate($exception, $db);
									if(is_a($hydrateException, '\Exception'))
										throw new \Exception($hydrateException->getMessage(), $hydrateException->getCode(), $hydrateException->getPrevious());
								}
							}
						}
					}
				}
				else{
					if($planning->getAffectedTime() > 0){
						$planningsToInsert[] = $planning;
					}
				}
			}

			if(!empty($planningsToInsert)){
				foreach($planningsToInsert as $planningToInsert){
					$insertPlanning = Managing_DailyPlanning::insert($planningToInsert, $db);
					if(is_a($insertPlanning, '\Exception')){
						throw new \Exception($insertPlanning->getMessage(), $insertPlanning->getCode(), $insertPlanning->getPrevious());
					}
				}
			}

			$db->commit();

			die(json_encode(array(false, 'OK')));
		}
		catch(Exception $e){
			$db->rollBack();
			die(json_encode(array(true, $e->getMessage())));
		}
	}

	/**
	 * Gestion du Drag&Drop pour la planification quotidienne
	 */
	public function ajaxdragdailyhoursAction()
	{
		$debug = array();
		/*********************RECUPERATION DES DONNEES*************************/
		$affectationId = intval($this->_request->getParam('affectationId'));
		$blockId = intval($this->_request->getParam('blockId'));
		$oldDate = new DateTime($this->_request->getParam('oldDate'));
		$newDate = new DateTime($this->_request->getParam('newDate'));
		$nbFields = intval($this->_request->getParam('nbFields'));
		/*********************RECUPERATION DES DONNEES*************************/
		$debug['origin'] = array(
			'oldDate' => $oldDate->format('Y-m-d'),
			'newDate' => $newDate->format('Y-m-d')
		);

		$differenceDate = $oldDate->diff($newDate);
		$difference = $differenceDate->format('%R%a');
		if($difference > 0)
			$difference = (int)$differenceDate->format('%a');
		else
			$difference = (int)$differenceDate->format('%R%a');

		//Récupération du plannings concernés par le changement
		$planning = Managing_DailyPlanning::fetchByBlockId($blockId);

		$planning->diffDates($difference);
		$planning->setAffectationId($affectationId);

		Managing_DailyPlanning::hydrate($planning);
		
		die(json_encode(array(false, 'OK')));
	}

	public function exportplanningressourcehoursAction(){
		$this->exportplanningressource('hours');
	}

	public function exportplanningressourcepercentAction(){
		$this->exportplanningressource('percent');
	}

	public function exportplanningressource($mode = 'hours'){
		$startDate = new DateTime();
		$monthsAmountToPrint = 12;


		$month = (int)$startDate->format('m');
		$year = (int)$startDate->format('Y');
		$monthsToPrint = array();
		$resultArray = array();
		$teamsArray = array();
		$printedTeams = array(1, 2, 3);

		for($actualMonthNumber = 0; $actualMonthNumber < $monthsAmountToPrint; $actualMonthNumber++){
			$monthsToPrint[$year][] = $month;

			$sql = '	SELECT 	pe.contact_id as userId,
								pe.nom as lastName,
								pe.prenom as firstName,
								eq.equipe as team,
								sa.heures_jour as heures_jour,
								(
									SELECT  SUM(mt.time) as cumulHeures
									FROM affectation_mensualTime mt
									INNER JOIN projet_affectation pa
										ON mt.projet_affectation_id = pa.id
									WHERE pa.user_id = pe.contact_id
									AND month = "'.$month.'"
									AND year = "'.$year.'"
									AND pa.closed = 0
									GROUP BY pa.user_id
								) AS tempsAffecte
						FROM am_crm_salaries sa
						INNER JOIN am_crm_personnes pe
							ON sa.contact_id = pe.contact_id
						INNER JOIN am_crm_salaries_equipes eq
							ON eq.id = sa.equipe
						WHERE eq.id IN ('.implode(', ', $printedTeams).')
						ORDER BY eq.id DESC, pe.prenom, pe.nom
			';

			mysql_select_db(DB_PREFIX.'mulhouse');
			$fetch_users = mysql_query($sql);

			while($row = mysql_fetch_assoc($fetch_users)){
				$userId = (int)$row['userId'];
				$teamName = utf8_encode($row['team']);

				/**************************RECUPERATION DES HEURES A TRAVAILLER***************************/
				$beginDate = new DateTime($year.'-'.$month.'-01');
				$stopDate = new DateTime($beginDate->format('Y-m-d'));
				$stopDate->modify('last day of this month');
				$absencesDetails = Absence_Model_Conge::getAbsencesDetails($userId, $beginDate, $stopDate, 'mulhouse');
				$usersHours[$userId][$year][$month]['hours_available'] = 0;
				$usersHours[$userId][$year][$month]['hours_planned'] = 0;
				$usersHours[$userId][$year][$month]['hours_left'] = 0;

				$beginYear = (int)$beginDate->format('Y');
				$beginMonth = (int)$beginDate->format('m');
				$beginDay = (int)$beginDate->format('d');
				$endYear = (int)$stopDate->format('Y');
				$endMonth = (int)$stopDate->format('m');
				$endDay = (int)$stopDate->format('d');

				for($actualYear = $beginYear; $actualYear <= $endYear; $actualYear++){
					$startMonth = ($actualYear == $beginYear) ? $beginMonth : 1;
					$stopMonth = ($actualYear == $endYear) ? $endMonth : 12;
					for($actualMonth = $startMonth; $actualMonth <= $stopMonth; $actualMonth++){
						$thisMonth = new DateTime($actualYear.'-'.$actualMonth.'-01');
						$daysInMonth = $thisMonth->format('t');
						$startDay = ($actualYear == $beginYear && $actualMonth == $beginMonth) ? $beginDay : 01;
						$stopDay = ($actualYear == $endMonth && $actualMonth == $endMonth) ? $endDay : $daysInMonth;
						for($actualDay = $startDay; $actualDay <= $stopDay; $actualDay++){
							if(empty($absencesDetails[$actualYear][$actualMonth][$actualDay])){
								$usersHours[$userId][$actualYear][$actualMonth]['hours_available'] += (float)$row['heures_jour'];
								$usersHours[$userId][$actualYear][$actualMonth]['hours_left'] += (float)$row['heures_jour'];
							}
							else{
								if($absencesDetails[$actualYear][$actualMonth][$actualDay]['type'] == 1){
									$usersHours[$userId][$actualYear][$actualMonth]['hours_available'] += (float)$row['heures_jour']-(float)$row['heures_jour'] * $absencesDetails[$actualYear][$actualMonth][$actualDay]['duration'];
									$usersHours[$userId][$actualYear][$actualMonth]['hours_left'] += (float)$row['heures_jour']-(float)$row['heures_jour'] * $absencesDetails[$actualYear][$actualMonth][$actualDay]['duration'];
								}
							}
						}
					}
				}
				/**************************RECUPERATION DES HEURES A TRAVAILLER***************************/

				$usersHours[$userId]['lastName'] = utf8_encode($row['lastName']);
				$usersHours[$userId]['firstName'] = utf8_encode($row['firstName']);
				$usersHours[$userId]['heures_jour'] = (float)$row['heures_jour'];
				$myDebugNumber = (float)$row['tempsAffecte'];
				$usersHours[$userId][$year][$month]['hours_planned'] = $myDebugNumber;
				$usersHours[$userId][$year][$month]['hours_left'] -= $myDebugNumber;

				if(empty($teamHours[$teamName][$year][$month]['hours_available']))
					$teamHours[$teamName][$year][$month]['hours_available'] = 0;
				$teamHours[$teamName][$year][$month]['hours_available'] += $usersHours[$userId][$year][$month]['hours_available'];

				if(empty($teamHours[$teamName][$year][$month]['hours_planned']))
					$teamHours[$teamName][$year][$month]['hours_planned'] = 0;
				$teamHours[$teamName][$year][$month]['hours_planned'] += $usersHours[$userId][$year][$month]['hours_planned'];

				if(empty($teamHours[$teamName][$year][$month]['hours_left']))
					$teamHours[$teamName][$year][$month]['hours_left'] = 0;
				$teamHours[$teamName][$year][$month]['hours_left'] += $usersHours[$userId][$year][$month]['hours_left'];

				$teamHours[$teamName]['lastName'] = $teamName;

				$resultArray[$teamName]['team'] = $teamHours[$teamName];
				$resultArray[$teamName][$userId] = $usersHours[$userId];

				if(!in_array($teamName, $teamsArray)){
					$teamsArray[] = $teamName;
				}
			}
			$month++;
			if($month > 12){
				$month = 1;
				$year++;
			}
		}

		$totalRow = array('TOTAL');
		foreach($monthsToPrint as $year => $months){
			foreach($months as $month){
				$totalPlanned = 0;
				$totalAvailable = 0;
				$totalLeft = 0;
				foreach($resultArray as $domain => $resources){
					foreach($resources as $userId => $resource){
						if($userId == 'team'){
							if(empty($resource[$year][$month]['hours_planned'])) {
								$totalPlanned += 0;
							}
							else {
								$totalPlanned += $resource[$year][$month]['hours_planned'];
							}
							$totalAvailable += $resource[$year][$month]['hours_available'];
							$totalLeft += $resource[$year][$month]['hours_left'];
						}
					}
				}
				if($mode == 'hours')
					$amount = round($totalLeft);
				else
					$amount = round($totalPlanned / $totalAvailable * 100).'%';;

				$totalRow[] = $amount;
			}
		}


		include_once dirname(__FILE__).'/../../../base/library/Spreadsheet/Excel/Writer.php';
		$xls = new Spreadsheet_Excel_Writer();
		$xls->send('planning_ressources.xls');
		$xls->setCustomColor(12, 0, 191, 255); // Header
		$xls->setCustomColor(13, 233, 239, 248); // Stripes
		$xls->setCustomColor(14, 45, 99, 153); // Stats
		$xls->setCustomColor(15, 157,192,85); // Nom de teams

		$header1 =& $xls->addFormat();
		$header1->setBold();
		$header1->setFgColor(12);

		$sheet =& $xls->addWorksheet("Projets");
		$sheet->setInputEncoding('UTF-8');

		$header2 =& $xls->addFormat();
		$header2->setBold();
		$header2->setFgColor(14);
		$header2->setHAlign('left');
		$header2->setColor('white');

		$header3 =& $xls->addFormat();
		$header3->setBold();
		$header3->setFgColor(15);

		$l = 0;

		$rowArray = array('Equipe');
		foreach($monthsToPrint as $year => $months){
			foreach($months as $month){
				$rowArray[] = utf8_decode(monthNumberToString($month, true)).' '.$year;
			}
		}
		$sheet->writeRow(0, 0, $rowArray, $header2);
		++$l;
		$sheet->writeRow($l, 0, $totalRow, $header3);

		foreach($resultArray as $domain => $resources){
			foreach($resources as $userId => $resource){
				++$l;
				if($userId == 'team'){
					if($l > 2){
						$sheet->writeRow($l, 0);
						++$l;
					}
					$rowArray = array($resource['lastName']);
					foreach($monthsToPrint as $year => $months){
						foreach($months as $month){
							if(empty($resource[$year][$month]['hours_planned'])) {
								$hoursPlanned = 0;
							}
							else {
								$hoursPlanned = $resource[$year][$month]['hours_planned'];
							}
							$hoursAvailable = $resource[$year][$month]['hours_available'];
							$hoursLeft = $resource[$year][$month]['hours_left'];

							if($mode == 'hours')
								$rowArray[] = round($hoursLeft);
							else
								$rowArray[] = round($hoursPlanned / $hoursAvailable * 100).'%';
						}
					}
					$sheet->writeRow($l, 0, $rowArray, $header3);
				}
				else{
					$rowArray = array(utf8_decode($resource['firstName'].' '.$resource['lastName']));
					foreach($monthsToPrint as $year => $months){
						foreach($months as $month){
							if(empty($resource[$year][$month]['hours_planned'])) {
								$hoursPlanned = 0;
							}
							else {
								$hoursPlanned = $resource[$year][$month]['hours_planned'];
							}
							$hoursAvailable = $resource[$year][$month]['hours_available'];

							if($mode == 'hours')
								$rowArray[] = round($hoursAvailable - $hoursPlanned);
							else
								$rowArray[] = round($hoursPlanned / $hoursAvailable * 100).'%';
						}
					}
					$sheet->writeRow($l, 0, $rowArray);
				}
			}
		}

		$sheet->setColumn(0, 0, 30);
		$sheet->setColumn(1, 14, 15);

		$xls->close();
		die();
	}
}

function monthNumberToString($monthNumber, $complete = true, $language = 'fr')
{
	$months = array(
		'fr' => array(
			'comp' => array(
				1 => 'Janvier',
				2 => 'Février',
				3 => 'Mars',
				4 => 'Avril',
				5 => 'Mai',
				6 => 'Juin',
				7 => 'Juillet',
				8 => 'Août',
				9 => 'Septembre',
				10 => 'Octobre',
				11 => 'Novembre',
				12 => 'Décembre'
			),
			'abb' => array(
				1 => 'Janv',
				2 => 'Févr',
				3 => 'Mars',
				4 => 'Avr',
				5 => 'Mai',
				6 => 'Juin',
				7 => 'Juil',
				8 => 'Août',
				9 => 'Sept',
				10 => 'Oct',
				11 => 'Nov',
				12 => 'Déc'
			)
		),
		'en' => array(
			'comp' => array(
				1 => 'January',
				2 => 'February',
				3 => 'March',
				4 => 'April',
				5 => 'May',
				6 => 'June',
				7 => 'July',
				8 => 'August',
				9 => 'September',
				10 => 'October',
				11 => 'November',
				12 => 'December'
			),
			'abb' => array(
				1 => 'Jan',
				2 => 'Feb',
				3 => 'Mar',
				4 => 'Apr',
				5 => 'May',
				6 => 'Jun',
				7 => 'Jul',
				8 => 'Aou',
				9 => 'Sep',
				10 => 'Oct',
				11 => 'Nov',
				12 => 'Dec'
			)
		),

	);
	return $complete ? $months[$language]['comp'][$monthNumber] : $months[$language]['abb'][$monthNumber];
}
