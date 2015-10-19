<?php
    require_once '../../Configuration/ConfigUtilisee.php';
    include_once '../' . LIB . '/jsonwrapper/jsonwrapper.php';
    require_once '../../Modeles/Classes/ClassCnxPgObsOcc.php';
    require_once '../' . ENV . '/Outils/FiltreCarte.php';
    require_once '../' . ENV . '/Outils/FiltreGrille.php';
    require_once '../Adaptations/fGrille.php';
    require_once '../Adaptations/fAppli.php';
    

    $cnxPgObsOcc = new CnxPgObsOcc();
    // traitement particulier pour utiliser ici le champ "nom" de la table "IGN_BD_TOPO.COMMUNE"
    $where = str_replace(' commune ', ' IGN_BD_TOPO.COMMUNE.nom ', $where);
    $where = str_replace(' lieu_dit ', ' IGN_BD_TOPO.LIEU_DIT.nom ', $where);


    $sort = str_replace('commune', 'IGN_BD_TOPO.COMMUNE.nom', $sort);
    $sort = str_replace('lieu_dit', 'IGN_BD_TOPO.LIEU_DIT.nom', $sort);

    $req = "WITH tri AS (
        SELECT row_number() over (order by " . $sort . ' ' . $dir . " NULLS LAST), id_obs  
        FROM saisie.saisie_observation
        LEFT JOIN md.etude using(id_etude) left JOIN md.protocole USING(id_protocole) 
        LEFT JOIN md.personne AS numerisateurs ON numerisateur = numerisateurs.id_personne 
        LEFT JOIN md.personne AS validateurs ON validateur = validateurs.id_personne 
        LEFT JOIN ign_bd_topo.lieu_dit ON id_lieu_dit = ign_bd_topo.lieu_dit.id 
        LEFT JOIN ign_bd_topo.commune ON ign_bd_topo.commune.code_insee = saisie.saisie_observation.code_insee
        WHERE " . $where . ' AND ' . $and .' AND ' . $filter . ")
    SELECT 
        st_asgeojson(st_transform(SAISIE.SAISIE_OBSERVATION.geometrie, 4326)), 
        id_obs, heure_obs, date_obs, date_debut_obs, date_fin_obs, date_textuelle,
        SAISIE_OBSERVATION.regne, SAISIE_OBSERVATION.nom_vern, SAISIE_OBSERVATION.nom_complet,
        cd_nom, phylum, classe, ordre, famille, nom_valide, ST_GeometryType(SAISIE.SAISIE_OBSERVATION.geometrie),
        effectif, effectif_min, effectif_max, effectif_textuel, type_effectif, phenologie,
        precision, determination, id_waypoint, depart, latitude, elevation, IGN_BD_TOPO.COMMUNE.code_insee,
        substring(IGN_BD_TOPO.COMMUNE.code_insee from 1 for 2) AS dep, IGN_BD_TOPO.COMMUNE.nom AS commune,
        id_lieu_dit, IGN_BD_TOPO.LIEU_DIT.nom AS lieu_dit, observateur, md.liste_nom_auteur(observateur)
        AS observat, validateur, structure, md.liste_nom_structure(structure) AS struct,
        localisation, id_etude, nom_etude, id_protocole, libelle,
        diffusable, remarque_obs, statut_validation, decision_validation, url_photo,
        (NUMERISATEURS.nom || ' ' || NUMERISATEURS.prenom) AS numerisat, longitude,
        commentaire_photo, (VALIDATEURS.nom || ' ' || VALIDATEURS.prenom) AS validat
    FROM SAISIE.SAISIE_OBSERVATION JOIN tri USING(id_obs)
    LEFT JOIN MD.ETUDE USING(id_etude) 
    LEFT JOIN MD.PROTOCOLE USING(id_protocole) 
    LEFT JOIN MD.PERSONNE AS NUMERISATEURS ON numerisateur = NUMERISATEURS.id_personne 
    LEFT JOIN MD.PERSONNE AS VALIDATEURS ON validateur = VALIDATEURS.id_personne 
    LEFT JOIN IGN_BD_TOPO.LIEU_DIT ON id_lieu_dit = IGN_BD_TOPO.LIEU_DIT.id 
    LEFT JOIN IGN_BD_TOPO.COMMUNE ON IGN_BD_TOPO.COMMUNE.code_insee = SAISIE.SAISIE_OBSERVATION.code_insee
    WHERE row_number > " . $start . ' LIMIT ' . $limit;

    $rs = $cnxPgObsOcc->executeSql($req);
    $rsTot = $cnxPgObsOcc->executeSql('SELECT COUNT(*) FROM SAISIE.SAISIE_OBSERVATION
        LEFT JOIN MD.ETUDE USING(id_etude) LEFT JOIN MD.PROTOCOLE USING(id_protocole)
        LEFT JOIN MD.PERSONNE AS NUMERISATEURS ON numerisateur = NUMERISATEURS.id_personne
        LEFT JOIN MD.PERSONNE AS VALIDATEURS ON validateur = VALIDATEURS.id_personne
        LEFT JOIN IGN_BD_TOPO.LIEU_DIT ON id_lieu_dit = IGN_BD_TOPO.LIEU_DIT.id
        LEFT JOIN IGN_BD_TOPO.COMMUNE ON IGN_BD_TOPO.COMMUNE.code_insee = SAISIE.SAISIE_OBSERVATION.code_insee
        WHERE ' . $where . ' AND ' . $and . ' AND ' . $filter);
    $tot = pg_result($rsTot, 0, 0);
    $geoJson = '{"type": "FeatureCollection", "features": [';
    // cas particulier des géométries "NULL"
    $geomNull = '{"type": "MultiPolygon", "coordinates": []}'; // obligatoire pour SelectFeature (OpenLayers) et LegendPanel (GeoExt)
    $premiereFois = true;
    $rs = $cnxPgObsOcc->executeSql($req);
    while ($tab = pg_fetch_assoc($rs)) {
        $geom = $tab['st_asgeojson'];
        // nécessaire pour le FeatureReader.js bidouillé avec le rajout de la ligne "this.totalRecords = values.st_asgeojson"
        // pour faire fonctionner correctement le PagingToolbar avec le PageSizer
        $tab['st_asgeojson'] = $tot;
        if ($premiereFois) {
            if ($geom) {
                $geoJson .= '{"geometry": ' . $geom . ', "type": "Feature", "properties": ' . json_encode($tab) . '}';
            }
            else {
                $geoJson .= '{"geometry": ' . $geomNull . ', "type": "Feature", "properties": ' . json_encode($tab) . '}';
            }
            $premiereFois = false;
        }
        else {
            if ($geom) {
                $geoJson .= ', {"geometry": ' . $geom . ', "type": "Feature", "properties": ' . json_encode($tab) . '}';
            }
            else {
                $geoJson .= ', {"geometry": ' . $geomNull . ', "type": "Feature", "properties": ' . json_encode($tab) . '}';
            }
        }
    }
    echo $geoJson . ']}';
    unset($cnxPgObsOcc);
?>
