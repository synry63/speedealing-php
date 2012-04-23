<?php
/* Copyright (C) 2002-2006 Rodolphe Quiedeville <rodolphe@quiedeville.org>
 * Copyright (C) 2004-2010 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2004      Eric Seigne          <eric.seigne@ryxeo.com>
 * Copyright (C) 2003      Brian Fraval         <brian@fraval.org>
 * Copyright (C) 2006      Andre Cianfarani     <acianfa@free.fr>
 * Copyright (C) 2005-2012 Regis Houssin        <regis@dolibarr.fr>
 * Copyright (C) 2008      Patrick Raguin       <patrick.raguin@auguria.net>
 * Copyright (C) 2010-2011 Juanjo Menent        <jmenent@2byte.es>
 * Copyright (C) 2010-2012 Herve Prot           <herve.prot@symeos.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

/**
 *	\file       htdocs/societe/class/societe.class.php
 *	\ingroup    societe
 *	\brief      File for third party class
 */
require_once(DOL_DOCUMENT_ROOT."/core/class/commonobject.class.php");


/**
 *	Class to manage third parties objects (customers, suppliers, prospects...)
 */
class Societe extends CommonObject
{
    public $element='societe';
    //public $table_element = 'societe';
    public $fk_element='fk_soc';
    protected $childtables=array("propal","commande","facture","contrat","facture_fourn","commande_fournisseur");    // To test if we can delete object
    protected $ismultientitymanaged = 1;	// 0=No test on entity, 1=Test with field entity, 2=Test with link by societe

    /**
     *    Constructor
     *
     *    @param	DoliDB		$db		Database handler
     */
    public function Societe(couchClient $db)
    {
        parent::__construct($db);

        return 1;
    }

    /**
     *    Check properties of third party are ok (like name, third party codes, ...)
     *
     *    @return     int		0 if OK, <0 if KO
     */
    function verify()
    {
        $this->errors=array();

        $result = 0;
        $this->name	= trim($this->name);

        if (! $this->name)
        {
            $this->errors[] = 'ErrorBadThirdPartyName';
            $result = -2;
        }

        if ($this->client && $this->codeclient_modifiable())
        {
            // On ne verifie le code client que si la societe est un client / prospect et que le code est modifiable
            // Si il n'est pas modifiable il n'est pas mis a jour lors de l'update
            $rescode = $this->check_codeclient();
            if ($rescode <> 0)
            {
                if ($rescode == -1)
                {
                    $this->errors[] = 'ErrorBadCustomerCodeSyntax';
                }
                if ($rescode == -2)
                {
                    $this->errors[] = 'ErrorCustomerCodeRequired';
                }
                if ($rescode == -3)
                {
                    $this->errors[] = 'ErrorCustomerCodeAlreadyUsed';
                }
                if ($rescode == -4)
                {
                    $this->errors[] = 'ErrorPrefixRequired';
                }
                $result = -3;
            }
        }

        if ($this->fournisseur && $this->codefournisseur_modifiable())
        {
            // On ne verifie le code fournisseur que si la societe est un fournisseur et que le code est modifiable
            // Si il n'est pas modifiable il n'est pas mis a jour lors de l'update
            $rescode = $this->check_codefournisseur();
            if ($rescode <> 0)
            {
                if ($rescode == -1)
                {
                    $this->errors[] = 'ErrorBadSupplierCodeSyntax';
                }
                if ($rescode == -2)
                {
                    $this->errors[] = 'ErrorSupplierCodeRequired';
                }
                if ($rescode == -3)
                {
                    $this->errors[] = 'ErrorSupplierCodeAlreadyUsed';
                }
                if ($rescode == -5)
                {
                    $this->errors[] = 'ErrorprefixRequired';
                }
                $result = -3;
            }
        }

        return $result;
    }

    /**
     *      Update parameters of third party
     *
     *      @param	int		$id              			id societe
     *      @param  User	$user            			Utilisateur qui demande la mise a jour
     *      @param  int		$call_trigger    			0=non, 1=oui
     *		@param	int		$allowmodcodeclient			Inclut modif code client et code compta
     *		@param	int		$allowmodcodefournisseur	Inclut modif code fournisseur et code compta fournisseur
     *		@param	string	$action						'create' or 'update'
     *      @return int  			           			<0 if KO, >=0 if OK
     */
    function update($id, $user='', $call_trigger=1, $allowmodcodeclient=0, $allowmodcodefournisseur=0, $action='update')
    {
        global $langs,$conf;
        require_once(DOL_DOCUMENT_ROOT."/core/lib/functions2.lib.php");

		$error=0;

        dol_syslog(get_class($this)."::Update id=".$id." call_trigger=".$call_trigger." allowmodcodeclient=".$allowmodcodeclient." allowmodcodefournisseur=".$allowmodcodefournisseur);

        // For triggers
        //if ($call_trigger) $this->oldobject = dol_clone($this);

        $now=dol_now();
        
        // Clean parameters
        $this->tel			= preg_replace("/\s/","",$this->tel);
        $this->tel			= preg_replace("/\./","",$this->tel);
        $this->fax			= preg_replace("/\s/","",$this->fax);
        $this->fax			= preg_replace("/\./","",$this->fax);
        $this->url			= $this->url?clean_url($this->url,0):'';
        $this->tms                      = $now;

        $this->tva_intra	= dol_sanitizeFileName($this->tva_intra,'');

        // Local taxes

        $this->capital=price2num(trim($this->capital),'MT');

        // For automatic creation
        if ($this->code_client == -1) $this->get_codeclient($this->prefix_comm,0);
        if ($this->code_fournisseur == -1) $this->get_codefournisseur($this->prefix_comm,1);

        // Check parameters
        if (! empty($conf->global->SOCIETE_MAIL_REQUIRED) && ! isValidEMail($this->email))
        {
            $langs->load("errors");
            $this->error = $langs->trans("ErrorBadEMail",$this->email);
            return -1;
        }

        // Check name is required and codes are ok or unique.
        // If error, this->errors[] is filled
        $result = $this->verify();

        if ($result >= 0)
        {
            dol_syslog(get_class($this)."::Update verify ok");

            unset($this->nom); //TODO supprimer
            unset($this->departement); //TODO supprimer
            unset($this->country_code); // TODO supprimer
            $this->record();
            dol_syslog(get_class($this)."::Update json=".  json_encode($this));
            
            //TODO - faire la suite...
            return 1;
            
            if ($resql)
            {
                unset($this->country_code);
                unset($this->country);
                unset($this->state_code);
                unset($this->state);

                // Si le fournisseur est classe on l'ajoute
                $this->AddFournisseurInCategory($this->fournisseur_categorie);

                // Actions on extra fields (by external module or standard code)
                include_once(DOL_DOCUMENT_ROOT.'/core/class/hookmanager.class.php');
                $hookmanager=new HookManager($this->db);
                $hookmanager->initHooks(array('thirdparty_extrafields'));
                $parameters=array('socid'=>$this->id);
                $reshook=$hookmanager->executeHooks('insertExtraFields',$parameters,$this,$action);    // Note that $action and $object may have been modified by some hooks
                if (empty($reshook))
                {
                    $result=$this->insertExtraFields();
                    if ($result < 0)
                    {
                        $error++;
                    }
                }
                else if ($reshook < 0) $error++;

                if (! $error && $call_trigger)
                {
                    // Appel des triggers
                    include_once(DOL_DOCUMENT_ROOT . "/core/class/interfaces.class.php");
                    $interface=new Interfaces($this->db);
                    $result=$interface->run_triggers('COMPANY_MODIFY',$this,$user,$langs,$conf);
                    if ($result < 0) { $error++; $this->errors=$interface->errors; }
                    // Fin appel triggers
                }

                if (! $error)
                {
                    dol_syslog(get_class($this)."::Update success");
                    return 1;
                }
                else
                {
                    return -1;
                }
            }
            else
            {
                if ($this->db->errno() == 'DB_ERROR_RECORD_ALREADY_EXISTS')
                {
                    // Doublon
                    $this->error = $langs->trans("ErrorDuplicateField");
                    $result =  -1;
                }
                else
                {

                    $this->error = $langs->trans("Error sql=".$sql);
                    dol_syslog(get_class($this)."::Update fails update sql=".$sql, LOG_ERR);
                    $result =  -2;
                }
                return $result;
            }
        }
        else
        {
            dol_syslog(get_class($this)."::Update fails verify ".join(',',$this->errors), LOG_WARNING);
            return -3;
        }
    }

    /**
     *    Delete a third party from database and all its dependencies (contacts, rib...)
     *
     *    @param	int		$id     Id of third party to delete
     *    @return	int				<0 if KO, 0 if nothing done, >0 if OK
     */
    function delete($id)
    {
        global $user,$langs,$conf;
        require_once(DOL_DOCUMENT_ROOT."/core/lib/files.lib.php");

        dol_syslog(get_class($this)."::delete", LOG_DEBUG);
        $error = 0;

        // Test if child exists
        //$objectisused = $this->isObjectUsed($this->rowid); // TODO A reactivier
		if (empty($objectisused))
		{
        

            require_once(DOL_DOCUMENT_ROOT."/categories/class/categorie.class.php");
            $static_cat = new Categorie($this->db);
            $toute_categs = array();

            // Fill $toute_categs array with an array of (type => array of ("Categorie" instance))
            if ($this->client || $this->prospect)
            {
                $toute_categs ['societe'] = $static_cat->containing($this->id,2);
            }
            if ($this->fournisseur)
            {
                $toute_categs ['fournisseur'] = $static_cat->containing($this->id,1);
            }

            // Remove each "Categorie"
            foreach ($toute_categs as $type => $categs_type)
            {
                foreach ($categs_type as $cat)
                {
                    $cat->del_type($this, $type);
                }
            }
            
            return parent::delete();

            // TODO Supprimer les contacts
            
            // Remove contacts
            if (! $error)
            {
                $sql = "DELETE FROM ".MAIN_DB_PREFIX."socpeople";
                $sql.= " WHERE fk_soc = " . $id;
                dol_syslog(get_class($this)."::delete sql=".$sql, LOG_DEBUG);
                if (! $this->db->query($sql))
                {
                    $error++;
                    $this->error .= $this->db->lasterror();
                    dol_syslog(get_class($this)."::delete erreur -1 ".$this->error, LOG_ERR);
                }
            }

            // Update link in member table
            if (! $error)
            {
                $sql = "UPDATE ".MAIN_DB_PREFIX."adherent";
                $sql.= " SET fk_soc = NULL WHERE fk_soc = " . $id;
                dol_syslog(get_class($this)."::delete sql=".$sql, LOG_DEBUG);
                if (! $this->db->query($sql))
                {
                    $error++;
                    $this->error .= $this->db->lasterror();
                    dol_syslog(get_class($this)."::delete erreur -1 ".$this->error, LOG_ERR);
                }
            }

            // Remove ban
            if (! $error)
            {
                $sql = "DELETE FROM ".MAIN_DB_PREFIX."societe_rib";
                $sql.= " WHERE fk_soc = " . $id;
                dol_syslog(get_class($this)."::Delete sql=".$sql, LOG_DEBUG);
                if (! $this->db->query($sql))
                {
                    $error++;
                    $this->error = $this->db->lasterror();
                    dol_syslog(get_class($this)."::Delete erreur -2 ".$this->error, LOG_ERR);
                }
            }

            // Removed extrafields
          	//$result=$this->deleteExtraFields($this);
            //if ($result < 0) $error++;

            if (! $error)
            {
            	// Additionnal action by hooks
                include_once(DOL_DOCUMENT_ROOT.'/core/class/hookmanager.class.php');
                $hookmanager=new HookManager($this->db);
                $hookmanager->initHooks(array('thirdparty_extrafields'));
                $parameters=array(); $action='delete';
                $reshook=$hookmanager->executeHooks('deleteThirdparty',$parameters,$this,$action);    // Note that $action and $object may have been modified by some hooks
                if (! empty($hookmanager->error))
                {
                    $error++;
                    $this->error=$hookmanager->error;
                }
            }

            // Remove third party
            if (! $error)
            {
                $sql = "DELETE FROM ".MAIN_DB_PREFIX."societe";
                $sql.= " WHERE rowid = " . $id;
                dol_syslog(get_class($this)."::delete sql=".$sql, LOG_DEBUG);
                if (! $this->db->query($sql))
                {
                    $error++;
                    $this->error = $this->db->lasterror();
                    dol_syslog(get_class($this)."::delete erreur -3 ".$this->error, LOG_ERR);
                }
            }

            if (! $error)
            {
                // Appel des triggers
                include_once(DOL_DOCUMENT_ROOT . "/core/class/interfaces.class.php");
                $interface=new Interfaces($this->db);
                $result=$interface->run_triggers('COMPANY_DELETE',$this,$user,$langs,$conf);
                if ($result < 0) { $error++; $this->errors=$interface->errors; }
                // Fin appel triggers
            }

            if (! $error)
            {
                $this->db->commit();

                // Delete directory
                $docdir = $conf->societe->multidir_output[$this->entity] . "/" . $id;
                if (file_exists($docdir))
                {
                    dol_delete_dir_recursive($docdir);
                }

                return 1;
            }
            else
            {
                $this->db->rollback();
                return -1;
            }
        }

    }

    /**
     *  Define third party as a customer
     *
     *	@return		int		<0 if KO, >0 if OK
     */
    function set_as_client()
    {
        if ($this->id)
        {
            $newclient=1;
            if ($this->client == 2 || $this->client == 3) $newclient=3;	//If prospect, we keep prospect tag
            $sql = "UPDATE ".MAIN_DB_PREFIX."societe";
            $sql.= " SET client = ".$newclient;
            $sql.= " WHERE rowid = " . $this->id;

            $resql=$this->db->query($sql);
            if ($resql)
            {
                $this->client = $newclient;
                return 1;
            }
            else return -1;
        }
        return 0;
    }

    /**
     *  Definit la societe comme un client
     *
     *  @param	float	$remise		Valeur en % de la remise
     *  @param  string	$note		Note/Motif de modification de la remise
     *  @param  User	$user		Utilisateur qui definie la remise
     *	@return	int					<0 if KO, >0 if OK
     */
    function set_remise_client($remise, $note, $user)
    {
        global $langs;

        // Nettoyage parametres
        $note=trim($note);
        if (! $note)
        {
            $this->error=$langs->trans("ErrorFieldRequired",$langs->trans("Note"));
            return -2;
        }

        dol_syslog(get_class($this)."::set_remise_client ".$remise.", ".$note.", ".$user->id);

        if ($this->id)
        {
            $this->db->begin();

            // Positionne remise courante
            $sql = "UPDATE ".MAIN_DB_PREFIX."societe ";
            $sql.= " SET remise_client = '".$remise."'";
            $sql.= " WHERE rowid = " . $this->id .";";
            $resql=$this->db->query($sql);
            if (! $resql)
            {
                $this->db->rollback();
                $this->error=$this->db->error();
                return -1;
            }

            // Ecrit trace dans historique des remises
            $sql = "INSERT INTO ".MAIN_DB_PREFIX."societe_remise ";
            $sql.= " (datec, fk_soc, remise_client, note, fk_user_author)";
            $sql.= " VALUES (".$this->db->idate(mktime()).", ".$this->id.", '".$remise."',";
            $sql.= " '".$this->db->escape($note)."',";
            $sql.= " ".$user->id;
            $sql.= ")";

            $resql=$this->db->query($sql);
            if (! $resql)
            {
                $this->db->rollback();
                $this->error=$this->db->error();
                return -1;
            }

            $this->db->commit();
            return 1;
        }
    }

    /**
     *    	Add a discount for third party
     *
     *    	@param	float	$remise     Amount of discount
     *    	@param  User	$user       User adding discount
     *    	@param  string	$desc		Reason of discount
     *      @param  float	$tva_tx     VAT rate
     *		@return	int					<0 if KO, id of discount record if OK
     */
    function set_remise_except($remise, $user, $desc, $tva_tx=0)
    {
        global $langs;

        // Clean parameters
        $remise = price2num($remise);
        $desc = trim($desc);

        // Check parameters
        if (! $remise > 0)
        {
            $this->error=$langs->trans("ErrorWrongValueForParameter","1");
            return -1;
        }
        if (! $desc)
        {
            $this->error=$langs->trans("ErrorWrongValueForParameter","3");
            return -2;
        }

        if ($this->id)
        {
            require_once(DOL_DOCUMENT_ROOT.'/core/class/discount.class.php');

            $discount = new DiscountAbsolute($this->db);
            $discount->fk_soc=$this->id;
            $discount->amount_ht=price2num($remise,'MT');
            $discount->amount_tva=price2num($remise*$tva_tx/100,'MT');
            $discount->amount_ttc=price2num($discount->amount_ht+$discount->amount_tva,'MT');
            $discount->tva_tx=price2num($tva_tx,'MT');
            $discount->description=$desc;
            $result=$discount->create($user);
            if ($result > 0)
            {
                return $result;
            }
            else
            {
                $this->error=$discount->error;
                return -3;
            }
        }
        else return 0;
    }

    /**
     *  Renvoie montant TTC des reductions/avoirs en cours disponibles de la societe
     *
     *	@param	User	$user		Filtre sur un user auteur des remises
     * 	@param	string	$filter		Filtre autre
     * 	@param	string	$maxvalue	Filter on max value for discount
     *	@return	int					<0 if KO, Credit note amount otherwise
     */
    function getAvailableDiscounts($user='',$filter='',$maxvalue=0)
    {
        require_once(DOL_DOCUMENT_ROOT.'/core/class/discount.class.php');

        $discountstatic=new DiscountAbsolute($this->db);
        $result=$discountstatic->getAvailableDiscounts($this,$user,$filter,$maxvalue);
        if ($result >= 0)
        {
            return $result;
        }
        else
        {
            $this->error=$discountstatic->error;
            return -1;
        }
    }

    /**
     *  Return array of sales representatives
     *
     *  @param	User	$user		Object user
     *  @return array       		Array of sales representatives of third party
     */
    function getSalesRepresentatives($user='')
    {
        global $conf;

        $reparray=array();

        $num = count($this->commerciaux);
        $i=0;
        if($num > 0)
            foreach ($this->commerciaux as $aRow)
            {
                $reparray[$i]['id']=$aRow;
                $i++;
            }
        return $reparray;
    }

    /**
     * Set the price level
     *
     * @param 	int		$price_level	Level of price
     * @param 	User	$user			Use making change
     * @return	int						<0 if KO, >0 if OK
     */
    function set_price_level($price_level, $user)
    {
        if ($this->id)
        {
        	$now=dol_now();

            $sql  = "UPDATE ".MAIN_DB_PREFIX."societe ";
            $sql .= " SET price_level = '".$price_level."'";
            $sql .= " WHERE rowid = " . $this->id;

            $this->db->query($sql);

            $sql  = "INSERT INTO ".MAIN_DB_PREFIX."societe_prices ";
            $sql .= " ( datec, fk_soc, price_level, fk_user_author )";
            $sql .= " VALUES ('".$this->db->idate($now)."',".$this->id.",'".$price_level."',".$user->id.")";

            if (! $this->db->query($sql) )
            {
                dol_print_error($this->db);
                return -1;
            }
            return 1;
        }
        return -1;
    }

    /**
     *	Add link to sales representative
     *
     *	@param	User	$user		Object user
     *	@param	int		$commid		Id of user
     *	@return	void
     */
    function add_commercial($user, $commid)
    {
        if ($this->id > 0 && $commid > 0)
        {
            $sql = "DELETE FROM  ".MAIN_DB_PREFIX."societe_commerciaux";
            $sql.= " WHERE fk_soc = ".$this->id." AND fk_user =".$commid;

            $this->db->query($sql);

            $sql = "INSERT INTO ".MAIN_DB_PREFIX."societe_commerciaux";
            $sql.= " ( fk_soc, fk_user )";
            $sql.= " VALUES (".$this->id.",".$commid.")";

            if (! $this->db->query($sql) )
            {
                dol_syslog(get_class($this)."::add_commercial Erreur");
            }
        }
    }

    /**
     *	Add link to sales representative
     *
     *	@param	User	$user		Object user
     *	@param	int		$commid		Id of user
     *	@return	void
     */
    function del_commercial($user, $commid)
    {
        if ($this->id > 0 && $commid > 0)
        {
            $sql  = "DELETE FROM  ".MAIN_DB_PREFIX."societe_commerciaux ";
            $sql .= " WHERE fk_soc = ".$this->id." AND fk_user =".$commid;

            if (! $this->db->query($sql) )
            {
                dol_syslog(get_class($this)."::del_commercial Erreur");
            }
        }
    }


    /**
     *    	Return a link on thirdparty (with picto)
     *
     *		@param	int		$withpicto		Add picto into link (0=No picto, 1=Include picto with link, 2=Picto only)
     *		@param	string	$option			Target of link ('', 'customer', 'prospect', 'supplier')
     *		@param	int		$maxlen			Max length of text
     *		@return	string					String with URL
     */
    function getNomUrl($withpicto=0,$option='',$maxlen=0)
    {
        global $conf,$langs;

        $name=$this->name;

        $result='';
        $lien=$lienfin='';

        if ($option == 'customer' || $option == 'compta')
        {
            if (($this->client == 1 || $this->client == 3) && empty($conf->global->SOCIETE_DISABLE_CUSTOMERS))  // Only customer
            {
                $lien = '<a href="'.DOL_URL_ROOT.'/comm/fiche.php?socid='.$this->id;
            }
            elseif($this->client == 2 && empty($conf->global->SOCIETE_DISABLE_PROSPECTS))   // Only prospect
            {
                $lien = '<a href="'.DOL_URL_ROOT.'/comm/prospect/fiche.php?socid='.$this->id;
            }
        }
        else if ($option == 'prospect' && empty($conf->global->SOCIETE_DISABLE_PROSPECTS))
        {
            $lien = '<a href="'.DOL_URL_ROOT.'/comm/prospect/fiche.php?socid='.$this->id;
        }
        else if ($option == 'supplier')
        {
            $lien = '<a href="'.DOL_URL_ROOT.'/fourn/fiche.php?socid='.$this->id;
        }
        // By default
        if (empty($lien))
        {
            $lien = '<a href="'.DOL_URL_ROOT.'/societe/soc.php?socid='.$this->id;
        }

        // Add type of canvas
        $lien.=(!empty($this->canvas)?'&amp;canvas='.$this->canvas:'').'">';
        $lienfin='</a>';

        if ($withpicto) $result.=($lien.img_object($langs->trans("ShowCompany").': '.$name,'company').$lienfin);
        if ($withpicto && $withpicto != 2) $result.=' ';
        $result.=$lien.($maxlen?dol_trunc($name,$maxlen):$name).$lienfin;

        return $result;
    }

    /**
     *    Return label of status (activity, closed)
     *
     *    @param	int		$mode       0=libelle long, 1=libelle court, 2=Picto + Libelle court, 3=Picto, 4=Picto + Libelle long
     *    @return   string        		Libelle
     */
    function getLibStatut($mode=0)
    {
        return $this->LibStatut($this->status,$mode);
    }

    /**
     *  Renvoi le libelle d'un statut donne
     *
     *  @param	int		$statut         Id statut
     *  @param	int		$mode           0=libelle long, 1=libelle court, 2=Picto + Libelle court, 3=Picto, 4=Picto + Libelle long, 5=Libelle court + Picto
     *  @return	string          		Libelle du statut
     */
    function LibStatut($statut,$mode=0)
    {
        global $langs;
        $langs->load('companies');

        if ($mode == 0)
        {
            if ($statut==0) return $langs->trans("ActivityCeased");
            if ($statut==1) return $langs->trans("InActivity");
        }
        if ($mode == 1)
        {
            if ($statut==0) return $langs->trans("ActivityCeased");
            if ($statut==1) return $langs->trans("InActivity");
        }
        if ($mode == 2)
        {
            if ($statut==0) return img_picto($langs->trans("ActivityCeased"),'statut6').' '.$langs->trans("ActivityCeased");
            if ($statut==1) return img_picto($langs->trans("InActivity"),'statut4').' '.$langs->trans("InActivity");
        }
        if ($mode == 3)
        {
            if ($statut==0) return img_picto($langs->trans("ActivityCeased"),'statut6');
            if ($statut==1) return img_picto($langs->trans("InActivity"),'statut4');
        }
        if ($mode == 4)
        {
            if ($statut==0) return img_picto($langs->trans("ActivityCeased"),'statut6').' '.$langs->trans("ActivityCeased");
            if ($statut==1) return img_picto($langs->trans("InActivity"),'statut4').' '.$langs->trans("InActivity");
        }
        if ($mode == 5)
        {
            if ($statut==0) return $langs->trans("ActivityCeased").' '.img_picto($langs->trans("ActivityCeased"),'statut6');
            if ($statut==1) return $langs->trans("InActivity").' '.img_picto($langs->trans("InActivity"),'statut4');
        }
    }

    /**
     * 	Return full address of third party
     *
     * 	@param		int			$withcountry		1=Add country into address string
     *  @param		string		$sep				Separator to use to build string
     *	@return		string							Full address string
     */
    function getFullAddress($withcountry=0,$sep="\n")
    {
        $ret='';
        if ($withcountry && $this->country_id && (empty($this->country_id) || empty($this->country)))
        {
            require_once(DOL_DOCUMENT_ROOT ."/core/lib/company.lib.php");
            $tmparray=getCountry($this->country_id,'all');
            $this->country     =$tmparray['label'];
        }

        if (in_array($this->country_code,array('US')))
        {
	        $ret.=($this->address?$this->address.$sep:'');
	        $ret.=trim($this->zip.' '.$this->town);
	        if ($withcountry) $ret.=($this->country?$sep.$this->country:'');
        }
        else
        {
	        $ret.=($this->address?$this->address.$sep:'');
	        $ret.=trim($this->zip.' '.$this->town);
	        if ($withcountry) $ret.=($this->country?$sep.$this->country:'');
        }
        return trim($ret);
    }


    /**
     *    Return list of contacts emails existing for third party
     *
     *	  @param	  int		$addthirdparty		1=Add also a record for thirdparty email
     *    @return     array       					Array of contacts emails
     */
    function thirdparty_and_contact_email_array($addthirdparty=0)
    {
        global $langs;

        $contact_emails = $this->contact_property_array('email');
        if ($this->email && $addthirdparty)
        {
            $this->name;
            // TODO: Tester si email non deja present dans tableau contact
            $contact_emails['thirdparty']=$langs->trans("ThirdParty").': '.dol_trunc($this->name,16)." &lt;".$this->email."&gt;";
        }
        return $contact_emails;
    }

    /**
     *    Return list of contacts mobile phone existing for third party
     *
     *    @return     array       Array of contacts emails
     */
    function thirdparty_and_contact_phone_array()
    {
        global $langs;

        $contact_phone = $this->contact_property_array('mobile');
        if ($this->tel)
        {
            // TODO: Tester si tel non deja present dans tableau contact
            $contact_phone['thirdparty']=$langs->trans("ThirdParty").': '.dol_trunc($this->name,16)." &lt;".$this->tel."&gt;";
        }
        return $contact_phone;
    }

    /**
     *    Return list of contacts emails or mobile existing for third party
     *
     *    @param	string	$mode       'email' or 'mobile'
     *    @return   array       		Array of contacts emails or mobile
     */
    function contact_property_array($mode='email')
    {
        $contact_property = array();

        $sql = "SELECT rowid, email, phone_mobile, name, firstname";
        $sql.= " FROM ".MAIN_DB_PREFIX."socpeople";
        $sql.= " WHERE fk_soc = '".$this->id."'";

        $resql=$this->db->query($sql);
        if ($resql)
        {
            $nump = $this->db->num_rows($resql);
            if ($nump)
            {
                $i = 0;
                while ($i < $nump)
                {
                    $obj = $this->db->fetch_object($resql);
                    if ($mode == 'email') $property=$obj->email;
                    else if ($mode == 'mobile') $property=$obj->phone_mobile;
                    $contact_property[$obj->rowid] = trim($obj->firstname." ".$obj->name)." &lt;".$property."&gt;";
                    $i++;
                }
            }
        }
        else
        {
            dol_print_error($this->db);
        }
        return $contact_property;
    }


    /**
     *    Renvoie la liste des contacts de cette societe
     *
     *    @return     array      tableau des contacts
     */
    function contact_array()
    {
        $contacts = array();

        $sql = "SELECT rowid, name, firstname FROM ".MAIN_DB_PREFIX."socpeople WHERE fk_soc = '".$this->id."'";
        $resql=$this->db->query($sql);
        if ($resql)
        {
            $nump = $this->db->num_rows($resql);
            if ($nump)
            {
                $i = 0;
                while ($i < $nump)
                {
                    $obj = $this->db->fetch_object($resql);
                    $contacts[$obj->rowid] = $obj->firstname." ".$obj->name;
                    $i++;
                }
            }
        }
        else
        {
            dol_print_error($this->db);
        }
        return $contacts;
    }

    /**
     *  Return property of contact from its id
     *
     *  @param	int		$rowid      id of contact
     *  @param  string	$mode       'email' or 'mobile'
     *  @return string  			email of contact
     */
    function contact_get_property($rowid,$mode)
    {
        $contact_property='';

        $sql = "SELECT rowid, email, phone_mobile, name, firstname";
        $sql.= " FROM ".MAIN_DB_PREFIX."socpeople";
        $sql.= " WHERE rowid = '".$rowid."'";

        $resql=$this->db->query($sql);
        if ($resql)
        {
            $nump = $this->db->num_rows($resql);

            if ($nump)
            {
                $obj = $this->db->fetch_object($resql);

                if ($mode == 'email') $contact_property = "$obj->firstname $obj->name <$obj->email>";
                else if ($mode == 'mobile') $contact_property = $obj->phone_mobile;
            }
            return $contact_property;
        }
        else
        {
            dol_print_error($this->db);
        }

    }


    /**
     *    Return bank number property of thirdparty
     *
     *    @return	string		Bank number
     */
    function display_rib()
    {
        global $langs;

        require_once DOL_DOCUMENT_ROOT . "/societe/class/companybankaccount.class.php";

        //$bac = new CompanyBankAccount($this->db);
        //$bac->fetch(0,$this->id);

        if ($bac->code_banque || $bac->code_guichet || $bac->number || $bac->cle_rib)
        {
            $rib = $bac->code_banque." ".$bac->code_guichet." ".$bac->number;
            $rib.=($bac->cle_rib?" (".$bac->cle_rib.")":"");
        }
        else
        {
            $rib=$langs->trans("NoRIB");
        }
        return $rib;
    }

    /**
     * Load this->bank_account attribut
     *
     * @return	int		1
     */
    function load_ban()
    {
        require_once DOL_DOCUMENT_ROOT . "/societe/class/companybankaccount.class.php";

        $bac = new CompanyBankAccount($this->db);
        $bac->fetch(0,$this->id);

        $this->bank_account = $bac;
        return 1;
    }

    /**
     * Check bank numbers
     *
     * @return int		<0 if KO, >0 if OK
     */
    function verif_rib()
    {
        $this->load_ban();
        return $this->bank_account->verif();
    }

    /**
     *  Attribut un code client a partir du module de controle des codes.
     *  Return value is stored into this->code_client
     *
     *	@param	Societe		$objsoc		Object thirdparty
     *	@param	int			$type		Should be 0 to say customer
     *  @return void
     */
    function get_codeclient($objsoc=0,$type=0)
    {
        global $conf;
        if ($conf->global->SOCIETE_CODECLIENT_ADDON)
        {
            $dirsociete=array_merge(array('/core/modules/societe/'),$conf->societe_modules);
            foreach ($dirsociete as $dirroot)
            {
                $res=dol_include_once($dirroot.$conf->global->SOCIETE_CODECLIENT_ADDON.".php");
                if ($res) break;
            }
            $var = $conf->global->SOCIETE_CODECLIENT_ADDON;
            $mod = new $var;

            $this->code_client = $mod->getNextValue($objsoc,$type);
            $this->prefixCustomerIsRequired = $mod->prefixIsRequired;

            dol_syslog(get_class($this)."::get_codeclient code_client=".$this->code_client." module=".$var);
        }
    }

    /**
     *  Attribut un code fournisseur a partir du module de controle des codes.
     *  Return value is stored into this->code_fournisseur
     *
     *	@param	Societe		$objsoc		Object thirdparty
     *	@param	int			$type		Should be 1 to say supplier
     *  @return void
     */
    function get_codefournisseur($objsoc=0,$type=1)
    {
        global $conf;
        if ($conf->global->SOCIETE_CODEFOURNISSEUR_ADDON)
        {
            $dirsociete=array_merge(array('/core/modules/societe/'),$conf->societe_modules);
            foreach ($dirsociete as $dirroot)
            {
                $res=dol_include_once($dirroot.$conf->global->SOCIETE_FOURNISSEUR_ADDON.".php");
                if ($res) break;
            }
            $var = $conf->global->SOCIETE_CODEFOURNISSEUR_ADDON;
            $mod = new $var;

            $this->code_fournisseur = $mod->getNextValue($objsoc,$type);

            dol_syslog(get_class($this)."::get_codefournisseur code_fournisseur=".$this->code_fournisseur." module=".$var);
        }
    }

    /**
     *    Verifie si un code client est modifiable en fonction des parametres
     *    du module de controle des codes.
     *
     *    @return     int		0=Non, 1=Oui
     */
    function codeclient_modifiable()
    {
        global $conf;
        if ($conf->global->SOCIETE_CODECLIENT_ADDON)
        {
            $dirsociete=array_merge(array('/core/modules/societe/'),$conf->societe_modules);
            foreach ($dirsociete as $dirroot)
            {
                $res=dol_include_once($dirroot.$conf->global->SOCIETE_CODECLIENT_ADDON.".php");
                if ($res) break;
            }

            $var = $conf->global->SOCIETE_CODECLIENT_ADDON;

            $mod = new $var;

            dol_syslog(get_class($this)."::codeclient_modifiable code_client=".$this->code_client." module=".$var);
            if ($mod->code_modifiable_null && ! $this->code_client) return 1;
            if ($mod->code_modifiable_invalide && $this->check_codeclient() < 0) return 1;
            if ($mod->code_modifiable) return 1;	// A mettre en dernier
            return 0;
        }
        else
        {
            return 0;
        }
    }


    /**
     *    Verifie si un code fournisseur est modifiable dans configuration du module de controle des codes
     *
     *    @return     int		0=Non, 1=Oui
     */
    function codefournisseur_modifiable()
    {
        global $conf;
        if ($conf->global->SOCIETE_CODEFOURNISSEUR_ADDON)
        {
            $dirsociete=array_merge(array('/core/modules/societe/'),$conf->societe_modules);
            foreach ($dirsociete as $dirroot)
            {
                $res=dol_include_once($dirroot.$conf->global->SOCIETE_CODEFOURNISSEUR_ADDON.".php");
                if ($res) break;
            }

            $var = $conf->global->SOCIETE_CODEFOURNISSEUR_ADDON;

            $mod = new $var;

            dol_syslog(get_class($this)."::codefournisseur_modifiable code_founisseur=".$this->code_fournisseur." module=".$var);
            if ($mod->code_modifiable_null && ! $this->code_fournisseur) return 1;
            if ($mod->code_modifiable_invalide && $this->check_codefournisseur() < 0) return 1;
            if ($mod->code_modifiable) return 1;	// A mettre en dernier
            return 0;
        }
        else
        {
            return 0;
        }
    }


    /**
     *    Check customer code
     *
     *    @return     int		0 if OK
     * 							-1 ErrorBadCustomerCodeSyntax
     * 							-2 ErrorCustomerCodeRequired
     * 							-3 ErrorCustomerCodeAlreadyUsed
     * 							-4 ErrorPrefixRequired
     */
    function check_codeclient()
    {
        global $conf;
        if ($conf->global->SOCIETE_CODECLIENT_ADDON)
        {
            $dirsociete=array_merge(array('/core/modules/societe/'),$conf->societe_modules);
            foreach ($dirsociete as $dirroot)
            {
                $res=dol_include_once($dirroot.$conf->global->SOCIETE_CODECLIENT_ADDON.".php");
                if ($res) break;
            }

            $var = $conf->global->SOCIETE_CODECLIENT_ADDON;

            $mod = new $var;

            dol_syslog(get_class($this)."::check_codeclient code_client=".$this->code_client." module=".$var);
            $result = $mod->verif($this->db, $this->code_client, $this, 0);
            return $result;
        }
        else
        {
            return 0;
        }
    }

    /**
     *    Check supplier code
     *
     *    @return     int		0 if OK
     * 							-1 ErrorBadCustomerCodeSyntax
     * 							-2 ErrorCustomerCodeRequired
     * 							-3 ErrorCustomerCodeAlreadyUsed
     * 							-4 ErrorPrefixRequired
     */
    function check_codefournisseur()
    {
        global $conf;
        if ($conf->global->SOCIETE_CODEFOURNISSEUR_ADDON)
        {
            $dirsociete=array_merge(array('/core/modules/societe/'),$conf->societe_modules);
            foreach ($dirsociete as $dirroot)
            {
                $res=dol_include_once($dirroot.$conf->global->SOCIETE_CODEFOURNISSEUR_ADDON.".php");
                if ($res) break;
            }

            $var = $conf->global->SOCIETE_CODEFOURNISSEUR_ADDON;

            $mod = new $var;

            dol_syslog(get_class($this)."::check_codefournisseur code_fournisseur=".$this->code_fournisseur." module=".$var);
            $result = $mod->verif($this->db, $this->code_fournisseur, $this, 1);
            return $result;
        }
        else
        {
            return 0;
        }
    }

    /**
     *    	Renvoie un code compta, suivant le module de code compta.
     *      Peut etre identique a celui saisit ou genere automatiquement.
     *      A ce jour seule la generation automatique est implementee
     *
     *    	@param	string	$type		Type of thirdparty ('customer' or 'supplier')
     *		@return	string				Code compta si ok, 0 si aucun, <0 si ko
     */
    function get_codecompta($type)
    {
        global $conf;

        if ($conf->global->SOCIETE_CODECOMPTA_ADDON)
        {
            $dirsociete=array_merge(array('/core/modules/societe/'),$conf->societe_modules);
            foreach ($dirsociete as $dirroot)
            {
                $res=dol_include_once($dirroot.$conf->global->SOCIETE_CODECOMPTA_ADDON.".php");
                if ($res) break;
            }

            $var = $conf->global->SOCIETE_CODECOMPTA_ADDON;
            $mod = new $var;

            // Defini code compta dans $mod->code
            $result = $mod->get_code($this->db, $this, $type);

            if ($type == 'customer') $this->code_compta = $mod->code;
            if ($type == 'supplier') $this->code_compta_fournisseur = $mod->code;

            return $result;
        }
        else
        {
            if ($type == 'customer') $this->code_compta = '';
            if ($type == 'supplier') $this->code_compta_fournisseur = '';

            return 0;
        }
    }

    /**
     *    Defini la societe mere pour les filiales
     *
     *    @param	int		$id     id compagnie mere a positionner
     *    @return	int     		<0 if KO, >0 if OK
     */
    function set_parent($id)
    {
        if ($this->id)
        {
            $sql  = "UPDATE ".MAIN_DB_PREFIX."societe ";
            $sql .= " SET parent = ".$id;
            $sql .= " WHERE rowid = " . $this->id .";";

            if ( $this->db->query($sql) )
            {
                return 1;
            }
            else
            {
                return -1;
            }
        }
    }

    /**
     *  Supprime la societe mere
     *
     *  @param	int		$id     id compagnie mere a effacer
     *  @return int     		<0 if KO, >0 if KO
     */
    function remove_parent($id)
    {
        if ($this->id)
        {
            $sql  = "UPDATE ".MAIN_DB_PREFIX."societe ";
            $sql .= " SET parent = null";
            $sql .= " WHERE rowid = " . $this->id .";";

            if ( $this->db->query($sql) )
            {
                return 1;
            }
            else
            {
                return -1;
            }
        }
    }

	/**
     *  Returns if a profid sould be verified
     *
     *  @param	int		$idprof		1,2,3,4 (Exemple: 1=siren,2=siret,3=naf,4=rcs/rm)
     *  @return boolean         	true , false
     */
    function id_prof_verifiable($idprof)
    {
	    global $conf;

     	switch($idprof)
        {
        	case 1:
        		$ret=(!$conf->global->SOCIETE_IDPROF1_UNIQUE?false:true);
        		break;
        	case 2:
        		$ret=(!$conf->global->SOCIETE_IDPROF2_UNIQUE?false:true);
        		break;
        	case 3:
        		$ret=(!$conf->global->SOCIETE_IDPROF3_UNIQUE?false:true);
        		break;
        	case 4:
        		$ret=(!$conf->global->SOCIETE_IDPROF4_UNIQUE?false:true);
        		break;
        	default:
        		$ret=false;
        }

        return $ret;
    }

	/**
     *    Verify if a profid exists into database for others thirds
     *
     *    @param	int		$idprof		1,2,3,4 (Example: 1=siren,2=siret,3=naf,4=rcs/rm)
     *    @param	string	$value		Value of profid
     *    @param	int		$socid		Id of society if update
     *    @return   boolean				true if exists, false if not
     */
    function id_prof_exists($idprof,$value,$socid=0)
    {
     	switch($idprof)
        {
        	case 1:
        		$field="siren";
        		break;
        	case 2:
        		$field="siret";
        		break;
        	case 3:
        		$field="ape";
        		break;
        	case 4:
        		$field="idprof4";
        		break;
        }

         //Verify duplicate entries
        $sql  = "SELECT COUNT(*) as idprof FROM ".MAIN_DB_PREFIX."societe WHERE ".$field." = '".$value."'";
        if($socid) $sql .= " AND rowid <> ".$socid;
        $resql = $this->db->query($sql);
        if ($resql)
        {
            $nump = $this->db->num_rows($resql);
            $obj = $this->db->fetch_object($resql);
            $count = $obj->idprof;
        }
        else
        {
            $count = 0;
            print $this->db->error();
        }
        $this->db->free($resql);

		if ($count > 0) return true;
		else return false;
    }

    /**
     *  Verifie la validite d'un identifiant professionnel en fonction du pays de la societe (siren, siret, ...)
     *
     *  @param	int			$idprof         1,2,3,4 (Exemple: 1=siren,2=siret,3=naf,4=rcs/rm)
     *  @param  Societe		$soc            Objet societe
     *  @return int             			<=0 if KO, >0 if OK
     *  TODO not in business class
     */
    function id_prof_check($idprof,$soc)
    {
        global $conf;

        $ok=1;

        if (! empty($conf->global->MAIN_DISABLEPROFIDRULES)) return 1;

        // Verifie SIREN si pays FR
        if ($idprof == 1 && $soc->country_code == 'FR')
        {
            $chaine=trim($this->idprof1);
            $chaine=preg_replace('/(\s)/','',$chaine);

            if (dol_strlen($chaine) != 9) return -1;

            $sum = 0;

            for ($i = 0 ; $i < 10 ; $i = $i+2)
            {
                $sum = $sum + substr($this->idprof1, (8 - $i), 1);
            }

            for ($i = 1 ; $i < 9 ; $i = $i+2)
            {
                $ps = 2 * substr($this->idprof1, (8 - $i), 1);

                if ($ps > 9)
                {
                    $ps = substr($ps, 0,1) + substr($ps, 1, 1);
                }
                $sum = $sum + $ps;
            }

            if (substr($sum, -1) != 0) return -1;
        }

        // Verifie SIRET si pays FR
        if ($idprof == 2 && $soc->country_code == 'FR')
        {
            $chaine=trim($this->idprof2);
            $chaine=preg_replace('/(\s)/','',$chaine);

            if (dol_strlen($chaine) != 14) return -1;
        }

        //Verify CIF/NIF/NIE if pays ES
        //Returns: 1 if NIF ok, 2 if CIF ok, 3 if NIE ok, -1 if NIF bad, -2 if CIF bad, -3 if NIE bad, 0 if unexpected bad
        if ($idprof == 1 && $soc->country_code == 'ES')
        {
            $string=trim($this->idprof1);
            $string=preg_replace('/(\s)/','',$string);
            $string = strtoupper($string);

            for ($i = 0; $i < 9; $i ++)
            $num[$i] = substr($string, $i, 1);

            //Check format
            if (!preg_match('/((^[A-Z]{1}[0-9]{7}[A-Z0-9]{1}$|^[T]{1}[A-Z0-9]{8}$)|^[0-9]{8}[A-Z]{1}$)/', $string))
            return 0;

            //Check NIF
            if (preg_match('/(^[0-9]{8}[A-Z]{1}$)/', $string))
            if ($num[8] == substr('TRWAGMYFPDXBNJZSQVHLCKE', substr($string, 0, 8) % 23, 1))
            return 1;
            else
            return -1;

            //algorithm checking type code CIF
            $sum = $num[2] + $num[4] + $num[6];
            for ($i = 1; $i < 8; $i += 2)
            $sum += substr((2 * $num[$i]),0,1) + substr((2 * $num[$i]),1,1);
            $n = 10 - substr($sum, strlen($sum) - 1, 1);

            //Chek special NIF
            if (preg_match('/^[KLM]{1}/', $string))
            if ($num[8] == chr(64 + $n) || $num[8] == substr('TRWAGMYFPDXBNJZSQVHLCKE', substr($string, 1, 8) % 23, 1))
            return 1;
            else
            return -1;

            //Check CIF
            if (preg_match('/^[ABCDEFGHJNPQRSUVW]{1}/', $string))
            if ($num[8] == chr(64 + $n) || $num[8] == substr($n, strlen($n) - 1, 1))
            return 2;
            else
            return -2;

            //Check NIE T
            if (preg_match('/^[T]{1}/', $string))
            if ($num[8] == preg_match('/^[T]{1}[A-Z0-9]{8}$/', $string))
            return 3;
            else
            return -3;

            //Check NIE XYZ
            if (preg_match('/^[XYZ]{1}/', $string))
            if ($num[8] == substr('TRWAGMYFPDXBNJZSQVHLCKE', substr(str_replace(array('X','Y','Z'), array('0','1','2'), $string), 0, 8) % 23, 1))
            return 3;
            else
            return -3;

            //Can not be verified
            return -4;
        }

        return $ok;
    }

    /**
     *   Renvoi url de verification d'un identifiant professionnal
     *
     *   @param		int		$idprof         1,2,3,4 (Exemple: 1=siren,2=siret,3=naf,4=rcs/rm)
     *   @param 	Societe	$soc            Objet societe
     *   @return	string          		url ou chaine vide si aucune url connue
     *   TODO not in business class
     */
    function id_prof_url($idprof,$soc)
    {
        global $conf,$langs;

        if (! empty($conf->global->MAIN_DISABLEPROFIDRULES)) return '';

        $url='';

        if ($idprof == 1 && $soc->country_code == 'FR') $url='http://www.societe.com/cgi-bin/recherche?rncs='.$soc->idprof1;
        if ($idprof == 1 && $soc->country_code == 'GB') $url='http://www.companieshouse.gov.uk/WebCHeck/findinfolink/';
        if ($idprof == 1 && $soc->country_code == 'ES') $url='http://www.e-informa.es/servlet/app/portal/ENTP/screen/SProducto/prod/ETIQUETA_EMPRESA/nif/'.$soc->idprof1;

        if ($url) return '<a target="_blank" href="'.$url.'">['.$langs->trans("Check").']</a>';
        return '';
    }

    /**
     *   Indique si la societe a des projets
     *
     *   @return     bool	   true si la societe a des projets, false sinon
     */
    function has_projects()
    {
        $sql = 'SELECT COUNT(*) as numproj FROM '.MAIN_DB_PREFIX.'projet WHERE fk_soc = ' . $this->id;
        $resql = $this->db->query($sql);
        if ($resql)
        {
            $nump = $this->db->num_rows($resql);
            $obj = $this->db->fetch_object($resql);
            $count = $obj->numproj;
        }
        else
        {
            $count = 0;
            print $this->db->error();
        }
        $this->db->free($resql);
        return ($count > 0);
    }


    /**
     *  Charge les informations d'ordre info dans l'objet societe
     *
     *  @param  int		$id     Id de la societe a charger
     *  @return	void
     */
    function info($id)
    {
        $sql = "SELECT s.rowid, s.nom as name, s.datec, s.datea,";
        $sql.= " fk_user_creat, fk_user_modif";
        $sql.= " FROM ".MAIN_DB_PREFIX."societe as s";
        $sql.= " WHERE s.rowid = ".$id;

        $result=$this->db->query($sql);
        if ($result)
        {
            if ($this->db->num_rows($result))
            {
                $obj = $this->db->fetch_object($result);

                $this->id = $obj->rowid;

                if ($obj->fk_user_creat) {
                    $cuser = new User($this->db);
                    $cuser->fetch($obj->fk_user_creat);
                    $this->user_creation     = $cuser;
                }

                if ($obj->fk_user_modif) {
                    $muser = new User($this->db);
                    $muser->fetch($obj->fk_user_modif);
                    $this->user_modification = $muser;
                }
                $this->ref			     = $obj->name;
                $this->date_creation     = $this->db->jdate($obj->datec);
                $this->date_modification = $this->db->jdate($obj->datea);
            }

            $this->db->free($result);

        }
        else
        {
            dol_print_error($this->db);
        }
    }

    /**
     *  Return if third party is a company (Business) or an end user (Consumer)
     *
     *  @return    boolean     true=is a company, false=a and user
     */
    function isACompany()
    {
        global $conf;

        // Define if third party is treated as company of not when nature is unknown
        $isacompany=empty($conf->global->MAIN_UNKNOWN_CUSTOMERS_ARE_COMPANIES)?0:1; // 0 by default
        if (! empty($this->tva_intra)) $isacompany=1;
        else if (! empty($this->typent_code) && in_array($this->typent_code,array('TE_PRIVATE'))) $isacompany=0;
        else if (! empty($this->typent_code) && in_array($this->typent_code,array('TE_SMALL','TE_MEDIUM','TE_LARGE'))) $isacompany=1;

        return $isacompany;
    }

    /**
     *  Charge la liste des categories fournisseurs
     *
     *  @return    int      0 if success, <> 0 if error
     */
    function LoadSupplierCateg()
    {
        $this->SupplierCategories = array();
        $sql = "SELECT rowid, label";
        $sql.= " FROM ".MAIN_DB_PREFIX."categorie";
        $sql.= " WHERE type = 1";

        $resql=$this->db->query($sql);
        if ($resql)
        {
            while ($obj = $this->db->fetch_object($resql) )
            {
                $this->SupplierCategories[$obj->rowid] = $obj->label;
            }
            return 0;
        }
        else
        {
            return -1;
        }
    }

    /**
     *  Charge la liste des categories fournisseurs
     *
     *	@param	int		$categorie_id		Id of category
     *  @return int      					0 if success, <> 0 if error
     */
    function AddFournisseurInCategory($categorie_id)
    {
        if ($categorie_id > 0)
        {
            $sql = "INSERT INTO ".MAIN_DB_PREFIX."categorie_fournisseur (fk_categorie, fk_societe) ";
            $sql.= " VALUES ('".$categorie_id."','".$this->id."');";

            if ($resql=$this->db->query($sql)) return 0;
        }
        else
        {
            return 0;
        }
        return -1;
    }


    /**
     *  Create a third party into database from a member object
     *
     *  @param	Member	$member		Object member
     * 	@param	string	$socname	Name of third party to force
     *  @return int					<0 if KO, id of created account if OK
     */
    function create_from_member($member,$socname='')
    {
        global $conf,$user,$langs;

        $name = $socname?$socname:$member->societe;
        if (empty($name)) $name=$member->getFullName($langs);

        // Positionne parametres
        $this->name=$name;
        $this->adresse=$member->adresse; // TODO obsolete
        $this->address=$member->adresse;
        $this->cp=$member->cp;			// TODO obsolete
        $this->zip=$member->cp;
        $this->ville=$member->ville;	// TODO obsolete
        $this->town=$member->ville;
        $this->pays_code=$member->country_code;	// TODO obsolete
        $this->country_code=$member->country_code;
        $this->pays_id=$member->country_id;	// TODO obsolete
        $this->country_id=$member->country_id;
        $this->tel=$member->phone;				// Prof phone
        $this->email=$member->email;

        $this->client = 1;				// A member is a customer by default
        $this->code_client = -1;
        $this->code_fournisseur = -1;

        $this->db->begin();

        // Cree et positionne $this->id
        $result=$this->create($user);
        if ($result >= 0)
        {
            $sql = "UPDATE ".MAIN_DB_PREFIX."adherent";
            $sql.= " SET fk_soc=".$this->id;
            $sql.= " WHERE rowid=".$member->id;

            dol_syslog(get_class($this)."::create_from_member sql=".$sql, LOG_DEBUG);
            $resql=$this->db->query($sql);
            if ($resql)
            {
                $this->db->commit();
                return $this->id;
            }
            else
            {
                $this->error=$this->db->error();
                dol_syslog(get_class($this)."::create_from_member - 1 - ".$this->error, LOG_ERR);

                $this->db->rollback();
                return -1;
            }
        }
        else
        {
            // $this->error deja positionne
            dol_syslog(get_class($this)."::create_from_member - 2 - ".$this->error." - ".join(',',$this->errors), LOG_ERR);

            $this->db->rollback();
            return $result;
        }
    }


    /**
     *  Initialise an instance with random values.
     *  Used to build previews or test instances.
     *	id must be 0 if object instance is a specimen.
     *
     *  @return	void
     */
    function initAsSpecimen()
    {
        global $user,$langs,$conf,$mysoc;

        $now=dol_now();

        // Initialize parameters
        $this->id=0;
        $this->name = 'THIRDPARTY SPECIMEN '.dol_print_date($now,'dayhourlog');
        $this->specimen=1;
        $this->zip='99999';
        $this->town='MyTown';
        $this->country_id=1;
        $this->country_code='FR';

        $this->code_client='CC-'.dol_print_date($now,'dayhourlog');
        $this->code_fournisseur='SC-'.dol_print_date($now,'dayhourlog');
        $this->capital=10000;
        $this->client=1;
        $this->prospect=1;
        $this->fournisseur=1;
        $this->tva_assuj=1;
        $this->tva_intra='EU1234567';
        $this->note_public='This is a comment (public)';
        $this->note='This is a comment (private)';

        $this->idprof1='idprof1';
        $this->idprof2='idprof2';
        $this->idprof3='idprof3';
        $this->idprof4='idprof4';
    }
    
    /**
     *  Generate parameter for datatables
     *
     *  @param $ref_css         name of the id datatables
     *  @return	json
     */
    
    function listSociete($ref_css)
        {
            global $langs, $conf;

            $obj->aaSorting = array(array(2, "desc"));

            $i=0;
            $obj->aoColumns[$i]->mDataProp = "name";
            $obj->aoColumns[$i]->sWidth = "20em";
            $obj->aoColumns[$i]->bUseRendered = true;
            $obj->aoColumns[$i]->bSearchable = true;
            $obj->aoColumns[$i]->fnRender= '%function(obj) {
                        var ar = [];
                        ar[ar.length] = $<a href=\"'.DOL_URL_ROOT.'/societe/soc.php?socid=$;
                        ar[ar.length] = obj.aData._id;
                        ar[ar.length] = $\"><img src=\"'.DOL_URL_ROOT.'/theme/'.$conf->theme.'/img/object_company.png\" border=\"0\" alt=\"Afficher mailing : $;
                        ar[ar.length] = obj.aData.name.toString();
                        ar[ar.length] = $\" title=\"Afficher soci&eacute;t&eacute;:$;
                        ar[ar.length] = obj.aData.name.toString();
                        ar[ar.length] = $\"></a> <a href=\"'.DOL_URL_ROOT.'/societe/soc.php?socid=$;
                        ar[ar.length] = obj.aData._id;
                        ar[ar.length] = $\">$;
                        ar[ar.length] = obj.aData.name.toString();
                        ar[ar.length] = $</a>$;
                        var str = ar.join("");
                        return str;
                        }%';
            $i++;
            $obj->aoColumns[$i]->mDataProp = "town";
            $obj->aoColumns[$i]->sWidth = "7em";
            $obj->aoColumns[$i]->sClass = "center";
            $obj->aoColumns[$i]->fnRender= '%function(obj) {
                                var str = obj.aData.town;
                                if(typeof str === $undefined$)
                                    str = null;
                                return str;
                        }%';
            $i++;
            $obj->aoColumns[$i]->mDataProp = "zip";
            $obj->aoColumns[$i]->sClass = "right";
            $obj->aoColumns[$i]->fnRender = '%function(obj) {
                            var str = obj.aData.zip;
                                if(typeof str === $undefined$)
                                    str = null;
                                return str;
                            }%';
            $i++;
            $obj->aoColumns[$i]->mDataProp = "category";
            $obj->aoColumns[$i]->fnRender = '%function(obj) {
                            var str = obj.aData.category;
                                if(typeof str === $undefined$)
                                    str = null;
                                return str;
                            }%';
            $i++;
            $obj->aoColumns[$i]->mDataProp = "commerciaux";
            $obj->aoColumns[$i]->fnRender = '%function(obj) {
                            var str = obj.aData.commerciaux;
                                if(typeof str === $undefined$)
                                    str = null;
                                return str;
                            }%';
            $i++;
            $obj->aoColumns[$i]->mDataProp = "idprof1";
            $obj->aoColumns[$i]->fnRender = '%function(obj) {
                            var str = obj.aData.idprof1;
                                if(typeof str === $undefined$)
                                    str = null;
                                return str;
                            }%';
            $i++;
            $obj->aoColumns[$i]->mDataProp = "idprof2";
            $obj->aoColumns[$i]->fnRender = '%function(obj) {
                            var str = obj.aData.idprof2;
                                if(typeof str === $undefined$)
                                    str = null;
                                return str;
                            }%';
            $i++;
            $obj->aoColumns[$i]->mDataProp = "potentiel";
            $obj->aoColumns[$i]->fnRender = '%function(obj) {
                            var str = obj.aData.potentiel;
                                if(typeof str === $undefined$)
                                    str = null;
                                return str;
                            }%';
            $i++;
            $obj->aoColumns[$i]->mDataProp = "fk_stcomm";
            $obj->aoColumns[$i]->sClass = "center";
            $obj->aoColumns[$i]->fnRender = '%function(obj) {
                            var status = new Array();
                            var stcomm = obj.aData.fk_stcomm;
                            if(typeof str === $undefined$)
                                stcomm = 0;
                        status[0] = new Array($'.$langs->trans('MailingStatusDraft').'$,0);
                        status[1] = new Array($'.$langs->trans('MailingStatusValidated').'$,1);
                        status[2] = new Array($'.$langs->trans('MailingStatusSentPartialy').'$,3);
                        status[3] = new Array($'.$langs->trans('MailingStatusSentCompletely').'$,6);
                        var ar = [];
                        ar[ar.length] = $<img src=\"'.DOL_URL_ROOT.'/theme/'.$conf->theme.'/img/statut$;
                        ar[ar.length] = status[stcomm][1];
                        ar[ar.length] = $.png\" border=\"0\" alt=\"Afficher mailing : $;
                        ar[ar.length] = $\" title=\"$;
                        ar[ar.length] = status[stcomm][0];
                        ar[ar.length] = $\">$;
                        var str = ar.join("");
                        return str;
                    }%';
            $i++;
            $obj->aoColumns[$i]->mDataProp = "tms";
            $obj->aoColumns[$i]->sType="date";
            $obj->aoColumns[$i]->sClass = "center";
            $obj->aoColumns[$i]->fnRender = '%function(obj) {
                        if(obj.aData.tms)
                        {
                            var date = new Date(obj.aData.tms*1000);
                            return date.toLocaleDateString();
                        }
                        else
                            return null;
                        }%';
            $i++;
            
            return $this->_datatables($obj,$ref_css);
        }

}

?>
