<?php
define('ROOTPATH', '..');
require_once ROOTPATH.'/config.php';
require_once './lib.offres.php';

/*
 * Gestion des droits liés au script courant
 */
$type = get_type($_SERVER['PHP_SELF']);
$template = get_template($_SERVER['PHP_SELF'], $type);


$xtpl = new XTemplate(SKIN_PATH . $template);
$xtpl->assign('rootpath', ROOTPATH);
$xtpl->assign("symbole_devise", DEVISE);
$xtpl->assign('server', array ('referer' => $_SERVER['HTTP_REFERER']));
$xtpl->assign("titre", "Offres");

if(!empty($_POST['offre']) && ($type == 'write'))
{
	$xtpl->assign('server', array ('referer' => $_SERVER['HTTP_REFERER']));
	$message = (!empty($_POST['lignes'])) ? check_post($_POST['offre'], $_POST['lignes']) : check_post($_POST['offre']);

	if (!empty($message))
	{
		$xtpl->assign('message', '<ul>'.$message.'</ul>');		

		// OFFRES
		$offre = $_POST['offre'];
		$offre['_date'] = $offre['date'];
		if (!empty($offre['etat']))
		{
			$ms_etats = get_etats_offre($offre['etat']);
			while ($etat = mysql_fetch_assoc($ms_etats))
			{
				$xtpl->assign('etat', $etat);
				$xtpl->parse('main.liste_etat_offres');
			}
		}
		
		$offre['interlocuteurs'] = get_interlocuteurs_offre_saved($offre['interlocuteurs']);
		$offre['cp_'.$offre['conditions_paiement'].'_sel'] = (!empty($offre['conditions_paiement'])) ? 'selected' : '';
	
		// Types d'envoi
		if (!empty($offre['type_envoi']))
		{
			foreach ($offre['type_envoi'] as $envoi)
			{
				$offre['envoi_'. trim($envoi)] = 'checked';
			}
		}
			
		$offre['view_accepte'] = "display:". ($offre['etat'] == ETAT_OFFRE_ACCEPTEE ? 'inline' : 'none');
	//	$offre['view_sended'] = "display:". ($offre['etat'] > 2 ? 'inline' : 'none');
		$offre['view_refus'] = "display:". ($offre['etat'] == ETAT_OFFRE_REFUS ? '' : 'none');
		$offre['view_remplacement'] = "display:". ($offre['etat'] == ETAT_OFFRE_REMPLACEE ? '' : 'none');
		$offre['type_date'] = sql_unique_value('SELECT type_date FROM '. OFFRES_ETATS .' WHERE etat_id = '.$offre['etat']);		
	
		
	
		$xtpl->assign('offre', $offre);


		// LIGNES
		if(!empty($_POST['lignes']))
		{
			foreach ($_POST['lignes'] as $ligne)
			{
				$ligne['checked_' ] = '';
				$ligne['checked_0'] = '';
				$ligne['checked_1'] = '';
				$ligne['checked_'. $ligne['reponse']] = 'checked';
				$xtpl->assign('ligne', $ligne);
				$xtpl->parse('main.liste_lignes');
			}
		}

		$xtpl->parse('main');
		$xtpl->out('main');
	}
	else
	{
		$xtpl->assign('server', array ('referer' => $_SERVER['HTTP_REFERER']));
		require_once 'editer_trt.php';
	}
}
else
{
	$_GET['offre_id'] = (empty($_GET['offre_id'])) ? 0 : $_GET['offre_id'];
	//if(!$_GET['mik']) die("petite maintenance tres rapide.. (michael)");
	$offre = get_offre($_GET['offre_id'], $type);

	$select = '<option value="">S&eacute;lectionnez une adresse</option>';
	
	if($_GET['offre_id'] && $offre['destinataire'])
	{
		$rad = mysql_query($q= 'SELECT adresse_id, CONCAT(rue,", ",codepostal,", ",ville) as string FROM '.ADRESSES.' WHERE `contact_id` = '.$offre['destinataire']);
		while($adresse = mysql_fetch_assoc($rad))
		{
			$select.= '<option value="'.$adresse['adresse_id'].'" '.
				($adresse['adresse_id']==$offre['adresse_envoi']?'selected="selected"':'').
				'>'.$adresse['string'].'</option>';
		}
	}

	$xtpl->assign('adresse_envoi', $select);
	
	if($offre['etat'] <= 2)
	{
		$offre['expediteur'] = 0;//$_SESSION[AM_UID][__user][contact_id]; // expéditeur de l'offre par défaut
	}
	
	$exp = mysql_fetch_assoc(mysql_query('SELECT CONCAT(p.prenom," ",p.nom) as expediteur_nom, su.email FROM '.PERSONNES.' p LEFT JOIN '.DB_PREFIX.'common.am_sec_users AS su ON su.contact_id = p.contact_id WHERE p.contact_id = '.((int) $offre['expediteur']).' LIMIT 1'));
	$offre['expediteur_nom'] = $exp['expediteur_nom'];
	$offre['expediteur_email'] = $exp['email'];
		
	// redacteur
	$red = mysql_fetch_assoc(mysql_query('SELECT CONCAT(p.prenom," ",p.nom) as redacteur_nom, su.email FROM '.PERSONNES.' p LEFT JOIN '.DB_PREFIX.'common.am_sec_users AS su ON su.contact_id = p.contact_id WHERE p.contact_id = '.((int) $offre['redacteur'])." LIMIT 1"));
	$offre['redacteur_nom'] = $red['redacteur_nom'];
	$offre['redacteur_email'] = $red['email'];
	
	
	$offre['description'] = (empty($offre['description'])) ? '' : $offre['description'];
	$offre['description'] = ($type == 'write') ? $offre['description'] : nl2br($offre['description']);
//	$offre['view_sended'] = "display:". ($offre['etat'] > 2 ? 'inline' : 'none');
	
	$documents = mysql_query("SELECT  am_documents.date, am_documents.nom, am_documents.document_id FROM ".DOCUMENTS_CONCERNE." LEFT JOIN ".DOCUMENTS." ON am_documents_concerne.document_id = am_documents.document_id WHERE am_documents_concerne.type = 'offre' AND id=".$offre['offre_id']);
	while($document = mysql_fetch_assoc($documents))
	{
		$date = explode('-',$document['date']);
		$document['date'] = $date[2].'/'.$date[1].'/'.$date[0];
		$document['type'] = "pi&egrave;ce jointe";
		$document['link'] = "/".$__section."/documents/download.php?document_id=".$document['document_id']."&lg=fr";
		$document['label'] = $document['nom'];
		
		$xtpl->assign('document',$document);
		$xtpl->parse('main.document');
	}
	
	$factures = mysql_query("SELECT *
FROM ".FACTURES."
WHERE `offre_id` =".$offre['offre_id']);
	while($facture = mysql_fetch_assoc($factures))
	{
		$date = explode('-',$facture['date']);
		$facture['date'] = $date[2].'/'.$date[1].'/'.$date[0];
		$facture['hide_delete'] = "none";
		$facture['type'] = "facture";
		$facture['link'] = "/".$__section."/factures/editer.php?facture_id=".$facture['facture_id']."&lg=fr";
		$facture['label'] = "facture n&deg; ".$facture['facture_id'];
		
		$xtpl->assign('document',$facture);
		$xtpl->parse('main.document');
	}

	// Récupération des conditions de paiement
	$offre['conditions'] = array();
	$conditions = mysql_query("SELECT *
		FROM `offres_conditions`
		WHERE `offre_id` =".$offre['offre_id']);

	while($condition = mysql_fetch_assoc($conditions))
	{
		$date = explode(' ', $condition['date']);
		$date = implode('/', array_reverse(explode('-', $date[0])));
		$condition['date'] = $date;
		$condition['evenement'] = utf8_encode($condition['evenement']);

		$offre['conditions'][] = $condition;
	}
	if (count($offre['conditions']))
		$offre['conditions'] = addslashes(json_encode($offre['conditions']));

	$project = false;
	if ($_GET['offre_id'] > 0) // Evite de ressortir les projets qui ne sont pas rattacher à une offre (projet de type abo)
	{
		$projets = mysql_query("SELECT *
	FROM `projet`
	WHERE `offre_id` =".$offre['offre_id']);
		while($projet = mysql_fetch_assoc($projets))
		{
			$date = explode(' ',$projet['date']);
			$date = explode('-',$date[0]);
			$projet['date'] = $date[2].'/'.$date[1].'/'.$date[0];
			$projet['hide_delete'] = "none";
			$projet['type'] = "projet";
			$projet['link'] = "/".$__section."/Projet/Gestion/detail/id/".$projet['id'];
			$projet['label'] = $projet['nom'];
			
			$xtpl->assign('document',$projet);
			$xtpl->parse('main.document');

			$project = true;

			// Documents liés au projet
			$path = dirname(__FILE__).'/../../uploads/p'.$__section{0}.$projet['id'];
			if (is_dir($path))
			{
				$current_directory = opendir($path);
				$files = array();

				if ($current_directory)
					while($file = readdir($current_directory))
					{
						if (!is_dir($path.'/'.$file) && $file != '.' && $file != '..' && ($fileinfo = pathinfo($file)) && array_key_exists('extension', $fileinfo)) 
						{
							$filename = pathinfo($file);
							$filename = substr($filename['filename'], 0, -14).'.'.$filename['extension'];
							$projet_doc = array(
								"type"        => "doc projet",
								"date"        => date('d/m/Y', filemtime($path.'/'.$file)),
								"hide_delete"  => "none",
								"label"        => utf8_encode($filename),
								"link"        => '/'.$__section.'/Projet/gestion/download/'.$projet['id'].'?filename=/uploads/p'.$__section{0}.$projet['id'].'/'.utf8_encode($file)
							);

							$xtpl->assign('document', $projet_doc);
							$xtpl->parse('main.document');
						}
					}
			}
		}
	}
		
	$ms_lignes = get_lignes_offre($offre['offre_id']);
	$offre['json'] = array("data"=>array());
	$etat = array(
	   '-1' => 'pending',
	   '0'  => 'rejected',
	   '1'  => 'accepted'
	);
	

	while ($line = mysql_fetch_object($ms_lignes))
	{
		$ms_line_details = get_details_lignes_offre($line->ligne_id);
		$cout_prevus = array();
		$temps_prevus = array();
		while ($line_details = mysql_fetch_object($ms_line_details))
		{
			if (empty($line_details->profil_id))
				$cout_prevus[] = $line_details->cout_prevu;
			else
				$temps_prevus[] = array('temps_prevu' => $line_details->temps_prevu, 'profil_id' => $line_details->profil_id);
		}
			
	    $element = array(
            "id" => (int) $line->ligne_id,
            "type_line" => $line->type_line,
            "name" => utf8_encode(stripcslashes($line->nom)),
            "prestation_id" => $line->prestation_id,
            "description" => utf8_encode(stripcslashes($line->description)),
            "comment"  => utf8_encode(stripcslashes($line->comment)),
            "price_unit" => ($line->price_unit != "0.00" ? $line->price_unit : $line->prix),
            "price" => $line->price,
            "quantity" => $line->quantite,
            "discount_value" => $line->discount_value,
            "discount_type" => $line->discount_type,
            "statut" => (($line->reponse === null) ? 'pending' : $etat[$line->reponse]),
	    	"statut_id" => (($line->reponse === null) ? -1 : $line->reponse),
	    	"planned_cost" => json_encode($cout_prevus),
	    	"planned_time" => json_encode($temps_prevus)
	    );
	    //echo var_export($line->reponse);
	    if(!$line->parent || !$offre['json']['data'][$line->parent]) $offre['json']['data'][$line->ligne_id] = $element;
	    else $offre['json']['data'][$line->parent]['prestations'][] = $element;    
	}
	
	include_once ROOTPATH.'/../library/prepend.php';
	include_once ROOTPATH.'/../library/Zend/Json.php';
	
	$offre['json'] = Zend_Json::encode($offre['json']);
	$xtpl->assign('offre', $offre);	
	
	if ($type == 'write')
	{
		$ms_etats = get_etats_offre($offre['etat']);
		while ($etat = mysql_fetch_assoc($ms_etats))
		{
			$xtpl->assign('etat', $etat);
			$xtpl->parse('main.liste_etat_offres');
		}	
	}
	else
	{
		$etat = get_etats_offre_read($offre['etat']);
		$xtpl->assign('etat', $etat);
		$xtpl->parse('main.liste_etat_offres');
	}
	
	if(in_array($_SESSION[AM_UID]['__user']['contact_id'], explode(",",RIGHTS_CREATE_PROJECTS)) && !$project)
	{
		$xtpl->parse('main.creerprojet');
	}
	

	$devise = '&euro;';
	if ($_GET['section'] === 'suisse')
		$devise = 'chf';
	$xtpl->assign('devise', $devise);
	
	$xtpl->parse('main');
	$xtpl->out('main');
}

?>
