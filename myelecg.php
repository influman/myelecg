<?php  
            $xml = "<?xml version=\"1.0\" encoding=\"ISO-8859-1\" ?>";  
	       //**********************************************************************************************************
            // V2.21 : Script de suivi de la consommation électrique en tarif unique global
            //*************************************** ******************************************************************
            // recuperation des infos depuis la requete
            // API CONSO INSTANTANEE - VAR1
            $api_instant = getArg("apii", $mandatory = true, $default = 'undefined');
            // API CONSO CUMULEE - VAR2
            $api_cumul = getArg("apic", $mandatory = true, $default = 'undefined');
            // DELTA COMPTEUR REEL - VAR3
            $delta = getArg("delta", $mandatory = false, $default = '0-0');
            // action
            $action = getArg("action", $mandatory = true, $default = '');
            // type
            $type = getArg("type", $mandatory = false, $default = '');
            // valeur passée en argument
            $arg_value = getArg("value", $mandatory = false, $default = '');
			// état qui donne HP HC directement
            $eco = getArg("eco", $mandatory = false, $default = '');
           // API DU PERIPHERIQUE APPELANT LE SCRIPT
            $api_script = getArg('eedomus_controller_module_id'); 
 
            $xml .= "<MYELEC>";
			if ($action == 'updatetarif' || $action == 'updateconso') {
				$maintenant = date("H").":".date("i");
				$xml .= "<APPEL>".$maintenant." ".$api_script."</APPEL>";
			}
            // LECTURE DS ECARTS DE COMPTEURS
            $delta_global = 0;
            if (!strpos($delta, "-")) {
                $delta_global = $delta;
                if ($delta_global == '') {
                    $delta_global = 0;
                }
            }
            $xml .= "<DELTA_GLOBAL>".$delta_global."</DELTA_GLOBAL>";      
            
			
			// voir le mode de calcul en fonction des états de compteurs disponibles
			// soit état consommation cumulée (par défaut si le deux dispos)
			// soit état consommation instantanée
			$type_cumul = false;
			$type_instant = false;
			if ($api_cumul != 'undefined' && $api_cumul != '' && $api_cumul != 'plugin.parameters.APIC') {
				$type_cumul = true;
				$api_compteur = $api_cumul;
				$xml .= "<COMPTEUR>CUMUL ".$api_compteur."</COMPTEUR>";
			}
			if ($api_instant != 'undefined' && $api_instant != '' && $api_instant != 'plugin.parameters.APII' && !$type_cumul) {
				$type_instant = true;
				$api_compteur = $api_instant;
				$xml .= "<COMPTEUR>INSTANT ".$api_compteur."</COMPTEUR>";
			}
			if (!$type_instant && !$type_cumul) {
				$xml .= "<COMPTEUR>INCONNU</COMPTEUR>";
			}
			
		// Un compteur a été paramétré
		// Initialisation des données
		if ($type_instant || $type_cumul) {
			if ($action == 'updatetarif' || $action == 'updateconso') {
            	// CHARGEMENT DES VARIABLES CODES API des périphériques Abonnement/tarif/hphc
            	// et définition du mode tarifaire en cours
            	$global = false;
            	$mesure = "PAS DE MESURE";
            	$abo_ok = false;
            	$aboglobal = false;
				$abohphc = false;
            	$tarif_dev = 0;
				$abobase = '';
				$tab_api_cpt_ok = false;
				$tab_api_cpt_init = array ("jour_global" => 0, "jour_prec_global" => 0, 
								   "mois_global" => 0, "mois_prec_global" => 0,
								   "annee_global" => 0, "annee_prec_global" => 0, "cpt_delta_global" => 0);
				$preload = loadVariable('MYELECGAPI_ABO_'.$api_compteur);
				if ($preload != '' && substr($preload, 0, 8) != "## ERROR") {
            	// charge le tableau des API abonnement de compteur
                    $api_abo = $preload;
					$preload = loadVariable('MYELECG_ABO_'.$api_compteur);
					if ($preload != '' && substr($preload, 0, 8) != "## ERROR") {
						$abobase = $preload;
						$abo_ok = true;
						if (!strpos($abobase, "GLOBAL")) {
							$abohphc = true;
						} else {
							$aboglobal = true;
							$global = true;
							$mesure = "GLOBAL";
						}
					}
				}
				$preload = loadVariable('MYELECGAPI_TARIF_'.$api_compteur);
				if ($preload != '' && substr($preload, 0, 8) != "## ERROR") {
					// charge le tableau des API tarif de compteur
                    $api_tarif = $preload;
					$value = getValue($api_tarif, true);
                    $tarif_dev = $value['value_text'];
				}
				$preload = loadVariable('MYELECGAPI_CPT_'.$api_compteur);
				if ($preload != '' && substr($preload, 0, 8) != "## ERROR") {
					// charge le tableau des API des différents compeuts J, J-1...
                    $tab_api_current_cpt = $preload;
                    if ($tab_api_current_cpt['jour_global'] != 0 and $tab_api_current_cpt['mois_global'] != 0 and $tab_api_current_cpt['annee_global'] != 0) {
						$tab_api_cpt_ok = true;
					}
					else {
						$tab_api_current_cpt = $tab_api_cpt_init;
						saveVariable('MYELECGAPI_CPT_'.$api_compteur, $tab_api_current_cpt);
					}
				}
				else {
					$tab_api_current_cpt = $tab_api_cpt_init;
					saveVariable('MYELECGAPI_CPT_'.$api_compteur, $tab_api_current_cpt);
				}
            }
			// ********************************************************************************************
            // lecture/maj des capteurs Abonnement/tarif/hphc associé à ce compteur (cumulé ou instantané)
            if ($action == 'updatetarif') {
            	// lui est un actionneur, on stocke son code API et on récupère la valeur
            	if ($type == 'abo' && $arg_value != '') {
            		if ($arg_value == 'poll') {
						if ($abo_ok) {
							$abo = $abobase;
						} else {
							$abo = "Sélectionner abonnement...";
						}
						$xml .= "<ABO>".$abo."</ABO>";
					} else {
						$abo = $arg_value;
						saveVariable('MYELECGAPI_ABO_'.$api_compteur, $api_script);
						if (!strpos($abo, "kVA")) {
							$abo = '';
						} else {
							saveVariable('MYELECG_ABO_'.$api_compteur, $abo);
						}
						die();
					}
                }
                // lui est un capteur qui retourne le tarif en cours
                if ($type == 'tarif') {
					$abo = '';
					$actual_hphc = '';
					$actual_tarif = 0;
			    	$value = getValue($api_script);
                    if ($value != '') {
                    	$actual_tarif = $value['value'];
                    }
                    if ($abo_ok ) {
                    	$value = getValue($api_abo);
						$abo = $abobase;
						if (!strpos($abo, "kVA")) {
							$abo = '';
						}
						$xml .= "<ABO>".$abo."</ABO>";
						if ($abo != '') {
                        	$tarif_abo = substr($abo, 0, 6);
                        	$actual_tarif = $tarif_abo;
                        	$actual_tarif = $actual_tarif."GLOBAL";
						}
					}
                    saveVariable('MYELECGAPI_TARIF_'.$api_compteur, $api_script);
					if ($actual_tarif == '') {
						$actual_tarif = 'En attente...';
					}
                    $xml .= "<TARIF>".$actual_tarif."</TARIF>";
                }
                	
            }
			//**********************************************************************************
			// Mise à jour de la consommation
            if ($action == 'updateconso') {
            	// restitution de la valeur actuel du compteur
            	$value = getValue($api_compteur);
            	$etat_compteur = $value['value'];
            	$xml .= "<VALCOMPTEUR>".$etat_compteur."</VALCOMPTEUR>";
            	$releve_conso = 0;
            	// restitution du précédent relevé du compteur (si état cumul)
				if ($type_cumul) {
					$mesure .= " (CUMUL)";
					$dernier_releve = $etat_compteur;
					$preload = loadVariable('MYELECG_LASTRELEVE_'.$api_compteur);
					if ($preload != '' && substr($preload, 0, 8) != "## ERROR") {
						$dernier_releve = $preload;
					} 
					$xml .= "<LASTVALCOMPTEUR>".$dernier_releve."</LASTVALCOMPTEUR>";
					if ($etat_compteur < $dernier_releve) {
							$releve_conso = round(($etat_compteur / 1000), 4);
					} 
					else {
						$releve_conso = round((($etat_compteur - $dernier_releve) / 1000), 4);
					}
					saveVariable('MYELECG_LASTRELEVE_'.$api_compteur, $etat_compteur);
					
					$preload = loadVariable('MYELECG_CPT_'.$api_compteur);
					if ($preload != '' && substr($preload, 0, 8) != "## ERROR") {
						$tab_cpt = $preload;
					} else {
						$tab_cpt['global'] = 0;
					}
					if ($global) {
						$tab_cpt['global'] = $etat_compteur;
					}		
						
            	} else if ($type_instant) { // a priori des watt mesurés en 1 mn
					$mesure .= " (INSTANT)";
					$releve_conso = round(($etat_compteur / 60000), 4);
				}
				
				// cout du relevé en kwh
				$cout = round(($releve_conso * (double)$tarif_dev), 6);
				
				// chargement des mesures précédentes
				$preload = loadVariable('MYELECG_RELEVES_'.$api_compteur);
				if ($preload != '' && substr($preload, 0, 8) != "## ERROR") {
					$tab_releves = $preload;
				} else {
					$tab_releves = array ("jour_global" => 0.0000, "jour_prec_global" => 0.0000, 
									 "mois_global" => 0.0000, "mois_prec_global" => 0.0000,
									 "annee_global" => 0.0000, "annee_prec_global" => 0.0000, "annee_prec_global_2" => 0.0000, "annee_prec_global_3" => 0.0000, "annee_prec_global_4" => 0.0000, "annee_prec_global_5" => 0.0000,
									 "lastmesure" => date('d')."-00:00");
				}
				$lasttime = substr($tab_releves['lastmesure'], 3, 5);
				$lastday = substr($tab_releves['lastmesure'], 0, 2);
				$razday = false;
				$razmois = false;
				$razannee = false;
				// si dernière mesure veille
				if ($lastday != date('d')) {
					$razday = true;
					if (date('j') == 1) {
						$razmois = true;
					}
					if (date('n') == 1 && $razmois) {
						$razannee = true;
					}
				}
				$preload = loadVariable('MYELECG_COUTS_'.$api_compteur);
				if ($preload != '' && substr($preload, 0, 8) != "## ERROR") {
					$tab_couts = $preload;
				} else {
					$tab_couts = array ("jour_global" => 0.000000, "jour_prec_global" => 0.000000, 
																 "mois_global" => 0.000000, "mois_prec_global" => 0.000000,
																 "annee_global" => 0.000000, "annee_prec_global" => 0.000000, "annee_prec_global_2" => 0.000000, "annee_prec_global_3" => 0.000000, "annee_prec_global_4" => 0.000000, "annee_prec_global_5" => 0.000000);
				}
				$releve_jour_global = $tab_releves['jour_global'];
				$releve_jour_prec_global = $tab_releves['jour_prec_global'];
				$releve_mois_global = $tab_releves['mois_global'];
				$releve_mois_prec_global = $tab_releves['mois_prec_global'];
				$releve_annee_global = $tab_releves['annee_global'];
				$releve_annee_prec_global = $tab_releves['annee_prec_global'];
				$releve_annee_prec_global_2 = $tab_releves['annee_prec_global_2'];
				$releve_annee_prec_global_3 = $tab_releves['annee_prec_global_3'];
				$releve_annee_prec_global_4 = $tab_releves['annee_prec_global_4'];
				$releve_annee_prec_global_5 = $tab_releves['annee_prec_global_5'];
				// ajout de la consommation au compteur respectif, releve et cout
				if ($global) {
					$releve_jour_global += $releve_conso;
					$releve_mois_global += $releve_conso;
					$releve_annee_global += $releve_conso;
					$tab_couts['jour_global'] += $cout;
					$tab_couts['mois_global'] += $cout;
					$tab_couts['annee_global'] += $cout;
				}
						
				// chargement prévisionnel annuel
				$prevannuel = "...";
				$preload = loadVariable('MYELECG_PREV_'.$api_compteur);
				if ($preload != '' && substr($preload, 0, 8) != "## ERROR") {
					$prevannuel = $preload;
				}
					
				// REMISES A ZERO
				if ($razday) {
					$nbprevcoutj = 0;
					$releve_jour_prec_global = $releve_jour_global;
					$prevcoutj = $tab_couts['jour_prec_global'];
					if ($prevcoutj > 0) {
						$nbprevcoutj = 1;
					}
					$tab_couts['jour_prec_global'] = $tab_couts['jour_global'];
					$prevcoutj += $tab_couts['jour_prec_global'];
					$nbprevcoutj++;
					$releve_jour_global = 0;
					$tab_couts['jour_global'] = 0;
				}
				
				if ($razmois) {
					$nbprevcout = 0;
					$releve_mois_prec_global = $releve_mois_global;
					$prevcout = $tab_couts['mois_prec_global'];
					if ($prevcout > 0) {
						$nbprevcout = 1;
					}
					$tab_couts['mois_prec_global'] = $tab_couts['mois_global'];
					$prevcout += $tab_couts['mois_prec_global'];
					$nbprevcout++;
					$releve_mois_global = 0;
					$tab_couts['mois_global'] = 0;
						
				}
				if ($razannee) {
					$releve_annee_prec_global_5 = $releve_annee_prec_global_4;
					$tab_couts['annee_prec_global_5'] = $tab_couts['annee_prec_global_4'];
					$releve_annee_prec_global_4 = $releve_annee_prec_global_3;
					$tab_couts['annee_prec_global_4'] = $tab_couts['annee_prec_global_3'];
					$releve_annee_prec_global_3 = $releve_annee_prec_global_2;
					$tab_couts['annee_prec_global_3'] = $tab_couts['annee_prec_global_2'];
					$releve_annee_prec_global_2 = $releve_annee_prec_global;
					$tab_couts['annee_prec_global_2'] = $tab_couts['annee_prec_global'];
					$releve_annee_prec_global = $releve_annee_global;
					$tab_couts['annee_prec_global'] = $tab_couts['annee_global'];
					$releve_annee_global = 0;
					$tab_couts['annee_global'] = 0;
				}
				$tab_releves['jour_global'] = $releve_jour_global;
				$tab_releves['jour_prec_global'] = $releve_jour_prec_global;
				$tab_releves['mois_global'] = $releve_mois_global;
				$tab_releves['mois_prec_global'] = $releve_mois_prec_global;
				$tab_releves['annee_global'] = $releve_annee_global;
				$tab_releves['annee_prec_global'] = $releve_annee_prec_global;
				$tab_releves['annee_prec_global_2'] = $releve_annee_prec_global_2;
				$tab_releves['annee_prec_global_3'] = $releve_annee_prec_global_3;
				$tab_releves['annee_prec_global_4'] = $releve_annee_prec_global_4;
				$tab_releves['annee_prec_global_5'] = $releve_annee_prec_global_5;
				$tab_releves['lastmesure'] = date('d')."-".$maintenant;
				saveVariable('MYELECG_RELEVES_'.$api_compteur, $tab_releves);
				saveVariable('MYELECG_COUTS_'.$api_compteur, $tab_couts);
				if ($type_cumul) {
					saveVariable('MYELECG_CPT_'.$api_compteur, $tab_cpt);
				}
				if ($global) {
					$mesure .= " ".$releve_jour_global." kwh";
				}
					
				if ($nbprevcoutj > 0 && $prevannuel == "...") {
					$prevannuel = round($prevcoutj * 365 / $nbprevcoutj,2);
				}
				if ($nbprevcout > 0) {
					$prevannuel = round($prevcout * 12 / $nbprevcout,2);
				}
				saveVariable('MYELECG_PREV_'.$api_compteur, $prevannuel);
				$mesure .= " (prev. ".$prevannuel." eur/an)";
				$xml .= "<STATUT>".$mesure."</STATUT>";
				
				// Mise à jour hors polling des compteurs J, J-1...
				if ($tab_api_cpt_ok) {
					setValue($tab_api_current_cpt['jour_global'], round($releve_jour_global,3)."kWh (".round($tab_couts['jour_global'],3)."eur", $update_only = true);
					setValue($tab_api_current_cpt['mois_global'], round($releve_mois_global,3)."kWh (".round($tab_couts['mois_global'],3)."eur", $update_only = true);
					setValue($tab_api_current_cpt['annee_global'], round($releve_annee_global,3)."kWh (".round($tab_couts['annee_global'],3)."eur", $update_only = true);
					if ($tab_api_current_cpt['jour_prec_global'] != 0) {
						setValue($tab_api_current_cpt['jour_prec_global'], round($releve_jour_prec_global,3)."kWh (".round($tab_couts['jour_prec_global'],3)."eur", $update_only = true);
					}
					if ($tab_api_current_cpt['mois_prec_global'] != 0) {
						setValue($tab_api_current_cpt['mois_prec_global'], round($releve_mois_prec_global,3)."kWh (".round($tab_couts['mois_prec_global'],3)."eur", $update_only = true);
					}
					if ($tab_api_current_cpt['annee_prec_global'] != 0) {
						setValue($tab_api_current_cpt['annee_prec_global'], round($releve_annee_prec_global,3)."kWh (".round($tab_couts['annee_prec_global'],3)."eur", $update_only = true);
					}
					if ($tab_api_current_cpt['cpt_delta_global'] != 0) {
							setValue($tab_api_current_cpt['cpt_delta_global'], $tab_cpt['global'] + $delta_global, $update_only = true);
					}
		       	}
            }
	    } else if ($action == 'updateconso') {
	    		$xml .= "<STATUT>En attente compteur...</STATUT>";
	    } else if ($action == 'updatetarif' ) {
	    	if ($type == 'abo') {
	    		$xml .= "<ABO>En attente compteur...</ABO>";
	    	}
	    	if ($type == 'tarif') {
	    		$xml .= "<TARIF>En attente compteur...</TARIF>";
	    	}
		}
		// ***********************************************************************************
        // lecture des capteurs
        if ($action == 'read') {
            $cpt = $delta_global;
            $tab_init = array ("jour_global" => 0.0000, "jour_prec_global" => 0.0000, 
								   "mois_global" => 0.0000, "mois_prec_global" => 0.0000,
								   "annee_global" => 0.0000, "annee_prec_global" => 0.0000, "annee_prec_global_2" => 0.0000, "annee_prec_global_3" => 0.0000, "annee_prec_global_4" => 0.0000, "annee_prec_global_5" => 0.0000,
								   "lastmesure" => date('d')."-00:00");
            // restitution de la valeur actuel du compteur
			$preload = loadVariable('MYELECG_RELEVES_'.$api_compteur);
			if ($preload != '' && substr($preload, 0, 8) != "## ERROR") {
               	$tab_init = $preload;
            }
            $xml .= "<JOUR_GLOBAL>".round($tab_init['jour_global'],3)."</JOUR_GLOBAL>";
            $xml .= "<MOIS_GLOBAL>".round($tab_init['mois_global'],3)."</MOIS_GLOBAL>";
            $xml .= "<ANNEE_GLOBAL>".round($tab_init['annee_global'],3)."</ANNEE_GLOBAL>";
            $xml .= "<ANNEE_PREC_GLOBAL>".round($tab_init['annee_prec_global'],3)."</ANNEE_PREC_GLOBAL>";
			$xml .= "<ANNEE_PREC_GLOBAL_2>".round($tab_init['annee_prec_global_2'],3)."</ANNEE_PREC_GLOBAL_2>";
			$xml .= "<ANNEE_PREC_GLOBAL_3>".round($tab_init['annee_prec_global_3'],3)."</ANNEE_PREC_GLOBAL_3>";
			$xml .= "<ANNEE_PREC_GLOBAL_4>".round($tab_init['annee_prec_global_4'],3)."</ANNEE_PREC_GLOBAL_4>";
			$xml .= "<ANNEE_PREC_GLOBAL_5>".round($tab_init['annee_prec_global_5'],3)."</ANNEE_PREC_GLOBAL_5>";
            $xml .= "<JOUR_PREC_GLOBAL>".round($tab_init['jour_prec_global'],3)."</JOUR_PREC_GLOBAL>";
            $xml .= "<MOIS_PREC_GLOBAL>".round($tab_init['mois_prec_global'],3)."</MOIS_PREC_GLOBAL>";
			//$xml .= "<LASTMESURE>".round($tab_init['lastmesure'],3)."</LASTMESURE>";
				
            if ($type_cumul) {
				$preload = loadVariable('MYELECG_CPT_'.$api_compteur);
				if ($preload != '' && substr($preload, 0, 8) != "## ERROR") {
					$tab_cpt = $preload;
					$cpt = $tab_cpt['global'] + $delta_global;
				} 
            }		
			$xml .= "<CPT_DELTA_GLOBAL>".$cpt."</CPT_DELTA_GLOBAL>";
                       	
            $tab_initc = array ("jour_global" => 0.000000, "jour_prec_global" => 0.000000, 
								   "mois_global" => 0.000000, "mois_prec_global" => 0.000000,
								   "annee_global" => 0.000000, "annee_prec_global" => 0.000000, "annee_prec_global_2" => 0.000000, "annee_prec_global_3" => 0.000000, "annee_prec_global_4" => 0.000000, "annee_prec_global_5" => 0.000000);
			// restitution de la valeur actuel des couts
			$preload = loadVariable('MYELECG_COUTS_'.$api_compteur);
			if ($preload != '' && substr($preload, 0, 8) != "## ERROR") {
				$tab_initc = $preload;
            }
            $xml .= "<JOUR_GLOBALC>".round($tab_initc['jour_global'],3)."</JOUR_GLOBALC>";
            $xml .= "<MOIS_GLOBALC>".round($tab_initc['mois_global'],3)."</MOIS_GLOBALC>";
            $xml .= "<ANNEE_GLOBALC>".round($tab_initc['annee_global'],3)."</ANNEE_GLOBALC>";
            $xml .= "<ANNEE_PREC_GLOBALC>".round($tab_initc['annee_prec_global'],3)."</ANNEE_PREC_GLOBALC>";
			$xml .= "<ANNEE_PREC_GLOBALC_2>".round($tab_initc['annee_prec_global_2'],3)."</ANNEE_PREC_GLOBALC_2>";
			$xml .= "<ANNEE_PREC_GLOBALC_3>".round($tab_initc['annee_prec_global_3'],3)."</ANNEE_PREC_GLOBALC_3>";
			$xml .= "<ANNEE_PREC_GLOBALC_4>".round($tab_initc['annee_prec_global_4'],3)."</ANNEE_PREC_GLOBALC_4>";
			$xml .= "<ANNEE_PREC_GLOBALC_5>".round($tab_initc['annee_prec_global_5'],3)."</ANNEE_PREC_GLOBALC_5>";
            $xml .= "<JOUR_PREC_GLOBALC>".round($tab_initc['jour_prec_global'],3)."</JOUR_PREC_GLOBALC>";
			$xml .= "<MOIS_PREC_GLOBALC>".round($tab_initc['mois_prec_global'],3)."</MOIS_PREC_GLOBALC>";
            
			if ($arg_value != '') {
				$preload = loadVariable('MYELECGAPI_CPT_'.$api_compteur);
				if ($preload != '' && substr($preload, 0, 8) != "## ERROR") {
					// charge le tableau des API des différents compeurs J, J-1...
                    $tab_api_current_cpt = $preload;
					$maj_tab_cpt = false;
                   	if ($arg_value == "jour_global" and $tab_api_current_cpt['jour_global'] != $api_script) {
						$tab_api_current_cpt['jour_global'] = $api_script;
						$maj_tab_cpt = true;
					}
					if ($arg_value == "mois_global" and $tab_api_current_cpt['mois_global'] != $api_script) {
						$tab_api_current_cpt['mois_global'] = $api_script;
						$maj_tab_cpt = true;
					}
					if ($arg_value == "annee_global" and $tab_api_current_cpt['annee_global'] != $api_script) {
						$tab_api_current_cpt['annee_global'] = $api_script;
						$maj_tab_cpt = true;
					}		
					if ($arg_value == "jour_prec_global" and $tab_api_current_cpt['jour_prec_global'] != $api_script) {
						$tab_api_current_cpt['jour_prec_global'] = $api_script;
						$maj_tab_cpt = true;
					}
					if ($arg_value == "mois_prec_global" and $tab_api_current_cpt['mois_prec_global'] != $api_script) {
						$tab_api_current_cpt['mois_prec_global'] = $api_script;
						$maj_tab_cpt = true;
					}
					if ($arg_value == "annee_prec_global" and $tab_api_current_cpt['annee_prec_global'] != $api_script) {
						$tab_api_current_cpt['annee_prec_global'] = $api_script;
						$maj_tab_cpt = true;
					}
					if ($arg_value == "cpt_delta_global" and $tab_api_current_cpt['cpt_delta_global'] != $api_script) {
						$tab_api_current_cpt['cpt_delta_global'] = $api_script;
						$maj_tab_cpt = true;
					}
					if ($maj_tab_cpt) {
						saveVariable('MYELECGAPI_CPT_'.$api_compteur, $tab_api_current_cpt);
					}
				}
			}			
        }
	    // ***********************************************************************************
        // mise à zéro
        if ($action == 'raz') {
			if ($type_instant || $type_cumul) {
				$tab_init = array ("jour_global" => 0.0000, "jour_prec_global" => 0.0000, 
								   "mois_global" => 0.0000, "mois_prec_global" => 0.0000,
								   "annee_global" => 0.0000, "annee_prec_global" => 0.0000, "annee_prec_global_2" => 0.0000, "annee_prec_global_3" => 0.0000, "annee_prec_global_4" => 0.0000, "annee_prec_global_5" => 0.0000,
								   "lastmesure" => date('d')."-00:00");
				$tab_initc = array ("jour_global" => 0.000000, "jour_prec_global" => 0.000000, 
								   "mois_global" => 0.000000, "mois_prec_global" => 0.000000,
								   "annee_global" => 0.000000, "annee_prec_global" => 0.000000, "annee_prec_global_2" => 0.000000, "annee_prec_global_3" => 0.000000, "annee_prec_global_4" => 0.000000, "annee_prec_global_5" => 0.000000);
				
				saveVariable('MYELECG_RELEVES_'.$api_compteur, $tab_init);
            	saveVariable('MYELECG_COUTS_'.$api_compteur, $tab_initc);
            	saveVariable('MYELECG_LASTRELEVE_'.$api_compteur, 0);
				
				$preload = loadVariable('MYELECG_CPT_'.$api_compteur);
				if ($preload != '' && substr($preload, 0, 8) != "## ERROR") {
					$tab_cpt = $preload;
					$tab_cpt['global'] = 0;
					saveVariable('MYELECG_CPT_'.$api_compteur, $tab_cpt);
				}	
				$preload = loadVariable('MYELECG_PREV_'.$api_compteur);
				if ($preload != '' && substr($preload, 0, 8) != "## ERROR") {
					saveVariable('MYELECG_PREV_'.$api_compteur, "...");
				}
				die();
			}
		} 
		// mise à jour manuelle
        if ($action == 'maj') {
			$tab_reinit = array ("jour_global" => 0.0000, "jour_prec_global" => 0.0000, 
								   "mois_global" => 0.0000, "mois_prec_global" => 0.0000,
								   "annee_global" => 0.0000, "annee_prec_global" => 0.0000, "annee_prec_global_2" => 0.0000, "annee_prec_global_3" => 0.0000, "annee_prec_global_4" => 0.0000, "annee_prec_global_5" => 0.0000,
								   "lastmesure" => date('d')."-00:00");
			$tab_reinitc = array ("jour_global" => 0.000000, "jour_prec_global" => 0.000000, 
								   "mois_global" => 0.000000, "mois_prec_global" => 0.000000,
								   "annee_global" => 0.000000, "annee_prec_global" => 0.000000, "annee_prec_global_2" => 0.000000, "annee_prec_global_3" => 0.000000, "annee_prec_global_4" => 0.000000, "annee_prec_global_5" => 0.000000);
				
			$xml .= "<MAJ>".$type." - ".$arg_value."</MAJ>";
			$preload = loadVariable('MYELECG_RELEVES_'.$api_compteur);
			if ($preload != '' && substr($preload, 0, 8) != "## ERROR") {
				$tab_reinit= $preload;
				$type = strtoupper($type);
				if ($type == 'JOUR_GLOBAL' && $arg_value != "") {
					$tab_reinit['jour_global'] = $arg_value;
					$xml .= "<MAJ_RESULT>OK</MAJ_RESULT>";
				}
				if ($type == 'JOUR_PREC_GLOBAL' && $arg_value != "") {
					$tab_reinit['jour_prec_global'] = $arg_value;
					$xml .= "<MAJ_RESULT>OK</MAJ_RESULT>";
				}
				if ($type == 'MOIS_GLOBAL' && $arg_value != "") {
					$tab_reinit['mois_global'] = $arg_value;
					$xml .= "<MAJ_RESULT>OK</MAJ_RESULT>";
				}
				if ($type == 'MOIS_PREC_GLOBAL' && $arg_value != "") {
					$tab_reinit['mois_prec_global'] = $arg_value;
					$xml .= "<MAJ_RESULT>OK</MAJ_RESULT>";
				}
				if ($type == 'ANNEE_GLOBAL' && $arg_value != "") {
					$tab_reinit['annee_global'] = $arg_value;
					$xml .= "<MAJ_RESULT>OK</MAJ_RESULT>";
				}
				if ($type == 'ANNEE_PREC_GLOBAL' && $arg_value != "") {
					$tab_reinit['annee_prec_global'] = $arg_value;
					$xml .= "<MAJ_RESULT>OK</MAJ_RESULT>";
				}
				if ($type == 'ANNEE_PREC_GLOBAL_2' && $arg_value != "") {
					$tab_reinit['annee_prec_global_2'] = $arg_value;
					$xml .= "<MAJ_RESULT>OK</MAJ_RESULT>";
				}
				if ($type == 'ANNEE_PREC_GLOBAL_3' && $arg_value != "") {
					$tab_reinit['annee_prec_global_3'] = $arg_value;
					$xml .= "<MAJ_RESULT>OK</MAJ_RESULT>";
				}
				if ($type == 'ANNEE_PREC_GLOBAL_4' && $arg_value != "") {
					$tab_reinit['annee_prec_global_4'] = $arg_value;
					$xml .= "<MAJ_RESULT>OK</MAJ_RESULT>";
				}
				if ($type == 'ANNEE_PREC_GLOBAL_5' && $arg_value != "") {
					$tab_reinit['annee_prec_global_5'] = $arg_value;
					$xml .= "<MAJ_RESULT>OK</MAJ_RESULT>";
				}
				
				
				saveVariable('MYELECG_RELEVES_'.$api_compteur, $tab_reinit);
				
			}
            		
		
			$preload = loadVariable('MYELECG_COUTS_'.$api_compteur);
			if ($preload != '' && substr($preload, 0, 8) != "## ERROR") {	
				$tab_reinitc = $preload;
				if ($type == 'JOUR_GLOBALC' && $arg_value != "") {
					$tab_reinitc['jour_global'] = $arg_value;
					$xml .= "<MAJ_RESULT>OK</MAJ_RESULT>";
				}
				if ($type == 'JOUR_PREC_GLOBALC' && $arg_value != "") {
					$tab_reinitc['jour_prec_global'] = $arg_value;
					$xml .= "<MAJ_RESULT>OK</MAJ_RESULT>";
				}
				if ($type == 'MOIS_GLOBALC' && $arg_value != "") {
					$tab_reinitc['mois_global'] = $arg_value;
					$xml .= "<MAJ_RESULT>OK</MAJ_RESULT>";
				}
				if ($type == 'MOIS_PREC_GLOBALC' && $arg_value != "") {
					$tab_reinitc['mois_prec_global'] = $arg_value;
					$xml .= "<MAJ_RESULT>OK</MAJ_RESULT>";
				}
				if ($type == 'ANNEE_GLOBALC' && $arg_value != "") {
					$tab_reinitc['annee_global'] = $arg_value;
					$xml .= "<MAJ_RESULT>OK</MAJ_RESULT>";
				}
				if ($type == 'ANNEE_PREC_GLOBALC' && $arg_value != "") {
					$tab_reinitc['annee_prec_global'] = $arg_value;
					$xml .= "<MAJ_RESULT>OK</MAJ_RESULT>";
				}
				if ($type == 'ANNEE_PREC_GLOBALC_2' && $arg_value != "") {
					$tab_reinitc['annee_prec_global_2'] = $arg_value;
					$xml .= "<MAJ_RESULT>OK</MAJ_RESULT>";
				}
				if ($type == 'ANNEE_PREC_GLOBALC_3' && $arg_value != "") {
					$tab_reinitc['annee_prec_global_3'] = $arg_value;
					$xml .= "<MAJ_RESULT>OK</MAJ_RESULT>";
				}
				if ($type == 'ANNEE_PREC_GLOBALC_4' && $arg_value != "") {
					$tab_reinitc['annee_prec_global_4'] = $arg_value;
					$xml .= "<MAJ_RESULT>OK</MAJ_RESULT>";
				}
				if ($type == 'ANNEE_PREC_GLOBALC_5' && $arg_value != "") {
					$tab_reinitc['annee_prec_global_5'] = $arg_value;
					$xml .= "<MAJ_RESULT>OK</MAJ_RESULT>";
				}
				saveVariable('MYELECG_COUTS_'.$api_compteur, $tab_reinitc);
			}
					
		} 
		
		// migration v1 à v2
        if ($action == 'migrate') {
			$tab_reinit = array ("jour_global" => 0.0000, "jour_prec_global" => 0.0000, 
								   "mois_global" => 0.0000, "mois_prec_global" => 0.0000,
								   "annee_global" => 0.0000, "annee_prec_global" => 0.0000,
								   "lastmesure" => date('d')."-00:00");
			$tab_reinitc = array ("jour_global" => 0.000000, "jour_prec_global" => 0.000000, 
								   "mois_global" => 0.000000, "mois_prec_global" => 0.000000,
								   "annee_global" => 0.000000, "annee_prec_global" => 0.000000);
				
			$preload = loadVariable('MYELECG_RELEVES');
			if ($preload != '' && substr($preload, 0, 8) != "## ERROR") {	
				$tab_releves = $preload;
				if (array_key_exists($api_compteur, $tab_releves)) {
					$tab_reinit = $tab_releves[$api_compteur];
					saveVariable('MYELECG_RELEVES_'.$api_compteur, $tab_reinit);
					
					$preload = loadVariable('MYELECG_COUTS');
					if ($preload != '' && substr($preload, 0, 8) != "## ERROR") {	
						$tab_couts= $preload;
						if (array_key_exists($api_compteur, $tab_couts)) {
							$tab_reinitc = $tab_couts[$api_compteur];
						}
						saveVariable('MYELECG_COUTS_'.$api_compteur, $tab_reinitc);
					}
					
					$preload = loadVariable('MYELECG_CPT');
					if ($preload != '' && substr($preload, 0, 8) != "## ERROR") {	
						$tab_cpt = $preload;
						if (array_key_exists($api_compteur, $tab_cpt)) {
							saveVariable('MYELECG_CPT_'.$api_compteur, $tab_cpt[$api_compteur]);
						}
					} 
					
					$preload = loadVariable('MYELECG_LASTRELEVE');
					if ($preload != '' && substr($preload, 0, 8) != "## ERROR") {	
						$tab_dernierreleve = $preload;
						if (array_key_exists($api_compteur, $tab_dernierreleve)) {
							saveVariable('MYELECG_LASTRELEVE_'.$api_compteur, $tab_dernierreleve[$api_compteur]);
						}
					} 
					$xml .= "<STATUT>MIGRATION OK</STATUT>";
				}
			}
		}
	    // copy api
        if ($action == 'copy') {
				$tab_reinit = array ("jour_global" => 0.0000, "jour_prec_global" => 0.0000, 
								   "mois_global" => 0.0000, "mois_prec_global" => 0.0000,
								   "annee_global" => 0.0000, "annee_prec_global" => 0.0000, "annee_prec_global_2" => 0.0000, "annee_prec_global_3" => 0.0000, "annee_prec_global_4" => 0.0000, "annee_prec_global_5" => 0.0000,
								   "lastmesure" => date('d')."-00:00");
				$tab_reinitc = array ("jour_global" => 0.000000, "jour_prec_global" => 0.000000, 
								   "mois_global" => 0.000000, "mois_prec_global" => 0.000000,
								   "annee_global" => 0.000000, "annee_prec_global" => 0.000000, "annee_prec_global_2" => 0.000000, "annee_prec_global_3" => 0.000000, "annee_prec_global_4" => 0.000000, "annee_prec_global_5" => 0.000000);
				
				$xml .= "<COPY>".$arg_value."</COPY>";
				$preload = loadVariable('MYELECG_RELEVES_'.$arg_value);
				if ($preload != '' && substr($preload, 0, 8) != "## ERROR") {
					$tab_reinit= $preload;
					saveVariable('MYELECG_RELEVES_'.$api_compteur, $tab_reinit);
						
            	}	
					
				$preload = loadVariable('MYELECG_COUTS_'.$arg_value);
				if ($preload != '' && substr($preload, 0, 8) != "## ERROR") {	
					$tab_reinitc = $preload;
					saveVariable('MYELECG_COUTS_'.$api_compteur, $tab_reinitc);
				}
				$preload = loadVariable('MYELECG_CPT_'.$arg_value);
				if ($preload != '' && substr($preload, 0, 8) != "## ERROR") {	
					$tab_cpt = $preload;
					if (array_key_exists($arg_value, $tab_cpt)) {
						saveVariable('MYELECG_CPT_'.$api_compteur, $tab_cpt[$arg_value]);
					}
				} 
				$preload = loadVariable('MYELECG_LASTRELEVE_'.$arg_value);
				if ($preload != '' && substr($preload, 0, 8) != "## ERROR") {	
					$tab_dernierreleve = $preload;
					if (array_key_exists($arg_value, $tab_dernierreleve)) {
						saveVariable('MYELECG_LASTRELEVE_'.$api_compteur, $tab_dernierreleve[$arg_value]);
					}
				} 
				$xml .= "<STATUT>COPY OK</STATUT>";				
        } 

	    $xml .= "</MYELEC>";
		sdk_header('text/xml');
		echo $xml;
?>
