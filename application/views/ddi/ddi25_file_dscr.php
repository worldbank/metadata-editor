<?php
$ddi=$this->ddi_writer;
$ddi->xpath_val($file, 'file_id');
?>
<?php foreach($data_files as $file):?>
<fileDscr ID="<?php echo $ddi->xpath_val($file, 'file_id'); ?>" URI="<?php  echo $ddi->xpath_val($file, 'file_name'); ?>">
  <fileTxt>
     <fileName><?php  echo $ddi->xpath_val($file, 'file_name'); ?></fileName>
    <?php /*
     <!-- fileCitation - DDI2.5 - citation element - WONT-SUPPORT -->
     <fileCitation><titlStmt><titl></titl></titlStmt></fileCitation>

    <!-- dataFingerprint - DDI2.5 
    
    Allows for assigning a hash value (digital fingerprint) to the data or data file. Set the 
    attribute flag to "data" when the hash value provides a digital fingerprint to the data 
    contained in the file regardless of the storage format (ASCII, SAS, binary, etc.). One approach 
    to compute a data fingerprint is the Universal Numerical Fingerprint (UNF). Set the attribute 
    flag to "dataFile" if the digital fingerprint is only for the data file in its current storage 
    format. Provide the digital fingerprint in digitalFingerprintValue and identify the algorithm 
    specification used (add version as a separate entry if it is not part of the specification entry).
    
    -->

    <dataFingerprint type="data">
      <digitalFingerprintValue>UNF:3:DaYlT6QSX9r0D50ye+tXpA== </digitalFingerprintValue> 
      <algorithmSpecification>UNF v5.0 Calculation Producture [http://thedata.org/book/unf-version-5-0]</algorithmSpecification>
      <algorithmVersion>UNF V5</algorithmVersion>
    </dataFingerprint>
  */ ?>
     <fileCont><?php echo $ddi->xpath_val($file, 'description'); ?></fileCont>
<?php /*     
     <fileStrc type="relational">
        <recGrp recGrp="F5" keyvar="V94 V95">
         <!-- recDimnsn - ddi2.5 
          
          Information about the physical characteristics of the record. The "level" attribute on this 
          element should be set to "record".
          
          -->
          <recDimnsn level="record">
            <varQnty>NOT-SUPPORTED** - Number of variables per record</varQnty>
            <caseQnty>NOT-SUPPORTED** -  Number of cases / Record Quantity</caseQnty>
            <logRecL>NOT-SUPPORTED** - Record Length / Logical Record Length</logRecL>
          </recDimnsn>

        </recGrp>

        <!--notes DDI2.5 -->
        <notes><?php echo $file['notes'];?></notes>
     </fileStrc>
*/ ?>     
     <dimensns>
        <caseQnty><?php echo $ddi->xpath_val($file, 'case_count'); ?></caseQnty>
        <varQnty><?php  echo $ddi->xpath_val($file, 'var_count'); ?></varQnty>
<?php /*        
        <!--logRecl - ddi2.5 
        Logical Record Length e.g. 25
        -->
        <logRecL>25</logRecL>

        <!--recPrCas - ddi2.5 
        Records per Case
        
        Records per case in the file. This element should be used for card-image data or other files 
        in which there are multiple records per case.

        <recPrCas>5</recPrCas>
        -->
        <recPrCas>5</recPrCas>

        <!--recNumTot - DDI2.5 
        Overall Number of Records

        Overall record count in file. Particularly helpful in instances such as files with multiple cards/decks or records per case.

        Example
        <dimensns> <recNumTot>2400</recNumTot> </dimensns>
        
        -->
        <recNumTot>5</recNumTot>
*/ ?>        
     </dimensns>
<?php /*     
     <fileType>N/A</fileType>
     <format>NOT-SUPPORTED** - Data Format e.g. delimited format, free format</format>
*/ ?>     
     <filePlac><?php echo $ddi->xpath_val($file, 'producer'); ?></filePlac>
     <dataChck><?php echo $ddi->xpath_val($file, 'data_checks'); ?></dataChck>
<?php /*
     <ProcStat>NOT-SUPPORTED** - Processing Status</ProcStat>
*/ ?>     
     <dataMsng><?php echo $ddi->xpath_val($file, 'missing_data'); ?></dataMsng>
<?php /*     <software>N/A</software> */?>
     <verStmt>
        <version><?php echo $ddi->xpath_val($file, 'version'); ?></version>
<?php /*        
      <verResp>NOT-SUPPORTED** - Version Responsibility Statement</verResp>
      <notes>NOT-SUPPORTED** - version notes</notes>
*/ ?>        
     </verStmt>         
  </fileTxt>
<?php /*
  <!-- NOT-SUPPORTED** - Location Map and sub items
  <locMap>
    <dataItem/>
    <CubeCoord/>
    <PhysLoc/>
  </locMap>

  example:
      <physLoc type="rectangular" recRef="R1" startPos="55" endPos="57" width="3"/> 
      <physLoc type="hierarchical" recRef="R6" startPos="25" endPos="25" width="1"/>
  -->
  <locMap>
    <dataItem>
      <physLoc type="rectangular" recRef="R1" startPos="55" endPos="57" width="3"/> 
    </dataItem>
  </locMap>
*/ ?>  
  <notes><?php echo $ddi->xpath_val($file, 'notes'); ?></notes>
</fileDscr>
<?php endforeach;?>

<?php return;?>
<dataDscr>
  <!-- varGRP section TODO -->
  <varGrp></varGrp>

  <?php foreach($variables as $variable):$variable=$variable['metadata'];?>        
    <var ID="<?php echo $variable['vid'];?>" name="<?php echo $variable['name'];?>" files="<?php echo $variable['file_id'];?>" dcml="<?php echo @$variable['var_dcml'];?>" intrvl="<?php echo @$variable['var_intrvl'];?>">
        <pre>
          <?php var_dump($variable);?>
        </pre>
      <labl><?php @$ddi->escape_text($variable['labl']);?></labl>
    </var>


    <var ID="<?php echo $variable['vid'];?>" name="<?php echo $variable['name'];?>" files="<?php echo $variable['file_id'];?>" dcml="<?php echo @$variable['var_dcml'];?>" intrvl="<?php echo @$variable['var_intrvl'];?>">
     <location StartPos="<?php @$ddi->escape_text($variable['loc_start_pos']);?>" EndPos="61" width="1" RecSegNo="1" />
     
  </var>



  <?php endforeach;?>


  <var ID="V94" name="hh_socialbenefit" files="F1" dcml="0" intrvl="contin">
     <location StartPos="61" EndPos="61" width="1" RecSegNo="1" />
     <labl>Sample variable with all fields</labl>
     <imputation>imputation text</imputation>
     <security>security</security>
     <embargo event="notBefore" date="2001-09-30">NOT-SUPPORTED** - The data associated with this variable/nCube will not become available until September 30, 2001, because of embargo provisions established by the data producers.</embargo>
     <respUnit>source of information</respUnit>
     <anlysUnit>NOT-SUPPORTED** -- Household</anlysUnit>
     <qstn>
        <preQTxt>pre question text</preQTxt>
        <qstnLit>literal question text</qstnLit>
        <postQTxt>post question text</postQTxt>
        <forward qstn="Q120 Q121 Q122 Q123 Q124">NOT-SUPPORTED** - If yes, please ask questions 120-124.</forward>
        <backward qstn="Q12 Q13 Q14 Q15">NOT-SUPPORTED** - For responses on a similar topic, see questions 12-15.</backward>
        <ivuInstr>interview instructions</ivuInstr>
     </qstn>
     
     <!--NOT-SUPPORTED** valrng - Range of Valid Data Values -->
     <valrng><item VALUE="1" /><item VALUE="2" /><item VALUE="3" /></valrng>
    
    <!--NOT-SUPPORTED** invalrng -Range of Invalid Data Values -->
     <invalrng> 
        <range UNITS="INT" min="98" max="99"></range> 
        <key> 
        98 DK 
        99 Inappropriate 
        </key> 
      </invalrng> 

    <!-- <undocCod> List of Undocumented Codes -->  
    <undocCod>NOT-SUPPORTED** - Responses for categories 9 and 10 are unavailable.</undocCod>        

    <universe clusion="I">universe here</universe>

    <!-- <TotlResp> Total Responses -->
    <TotlResp>NOT-SUPPORTED** - There are only 725 responses to this question since it was not asked in Tanzania.</TotlResp>

     <sumStat type="min">0</sumStat>
     <txt>variable definition here</txt>

     <stdCatgry date="1981" source="producer">NOT-SUPPORTED** - Standard Categories- U. S. Census of Population and Housing, Classified Index of Industries and Occupations </stdCatgry>
     
     <!--catgryGrp - NOTSUPPORTED** -->
     <catgryGrp><labl>NOT-SUPPORTED** - Category Group</labl></catgryGrp>

     <codInstr>recoding and derivation</codInstr>

     <!-- verStmt - DDI2.5 
     
     Version statement - not supported by NESSTAR
     
     -->
     <verStmt>
        <version date="2018-04" type="version type">variable version description</version>
        <verResp affiliation="version responsibility affliation">var version responsibility</verResp>
        <notes>var version notes</notes>
     </verStmt>

     <concept vocab="concept vocab" vocabURI="uri">concept1</concept>
     
     <!-- 
     
     Derivation - DDI2.5

     Used only in the case of a derived variable, this element provides both a description of 
     how the derivation was performed and the command used to generate the derived variable, as well 
     as a specification of the other variables in the study used to generate the derivation. The "var" 
     attribute provides the ID values of the other variables in the study used to generate this derived variable.
     
      - drvdesc - A textual description of the way in which this variable was derived. The element may be repeated to support multiple language expressions of the content.
      - drvcmd - The actual command used to generate the derived variable. The "syntax" attribute is used to indicate the command language employed (e.g., SPSS, SAS, Fortran, etc.). The element may be repeated to support multiple language expressions of the content.
     --> 

     <derivation>
        <drvdesc> VAR215.01 "Outcome of first pregnancy" (1988 NSFG=VAR611 PREGOUT1) If R has never been pregnant (VAR203 PREGNUM EQ 0) then OUTCOM01 is blank/inapplicable. Else, OUTCOM01 is transferred from VAR225 OUTCOME for R's 1st pregnancy. </drvdesc> 
        <drvcmd syntax="SPSS">RECODE V1 TO V3 (0=1) (1=0) (2=-1) INTO DEFENSE WELFAREHEALTH. </drvcmd>
     </derivation> 

     <varFormat type="numeric" schema="other" />

    <!-- geoMap - DDI2.5 

    This element is used to point, using a "URI" attribute, to an external map that displays the geography in question. The "levelno" attribute indicates the level of the geographic hierarchy relayed in the map. The "mapformat" attribute indicates the format of the map.
    
    -->
     <geoMap URI="http://url-to-map" mapformat="map-format"/>         
     <notes>variable notes</notes>
  </var>
  <var ID="V95" name="hh_pensions" files="F1" dcml="0" intrvl="contin">
     <location StartPos="61" EndPos="61" width="1" RecSegNo="1" />
     <labl>1:someone in the hh receives pension; 0:else</labl>
     <imputation>imputation</imputation>
     <security>security</security>
     <respUnit>source of information</respUnit>
     <qstn>
        <preQTxt>pre question text</preQTxt>
        <qstnLit>literal question</qstnLit>
        <postQTxt>post question text</postQTxt>
        <ivuInstr>interviewer instructions</ivuInstr>
     </qstn>
     <universe clusion="I">universe</universe>
     <txt>definition here</txt>
     <catgry>
        <catValu>1</catValu>
        <labl>category label 1</labl>
     </catgry>
     <catgry>
        <catValu>2</catValu>
        <labl>category label 2</labl>
        <txt>category text</txt>
     </catgry>
     <codInstr>recoding and derivation</codInstr>
     <concept vocab="vocab" vocabURI="uri">concept text</concept>
     <varFormat type="numeric" schema="other" />
     <notes>notes</notes>
  </var>
  <var ID="V97" name="name" files="F1" intrvl="discrete">
     <location width="15" RecSegNo="1" />
     <labl>person name</labl>
     <varFormat type="character" schema="other" />
  </var>
  <var ID="V99" name="dob" files="F1" intrvl="discrete">
     <location width="15" RecSegNo="1" />
     <labl>date of birth</labl>
     <varFormat type="character" formatname="Nesstar.date" schema="other" category="date" />
  </var>
  <var ID="V517" name="V5" files="F1" dcml="0" intrvl="discrete">
     <location width="15" RecSegNo="1" />
     <labl />
     <varFormat type="numeric" schema="other" />
  </var>
</dataDscr>
<otherMat level="study">
<!--<ihsn:item key="published">1</ihsn:item>-->
</otherMat>