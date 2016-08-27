<?php
namespace tcpdf\metadata;
use \tcpdf\parser\Parser;
use \tcpdf\basic\Statics;

//============================================================+
// Last Update : 2016-05-01
// Author      : Tibor Roskó - University of Debrecen
// -------------------------------------------------------------------
//
// Description : This is a PHP class for manipulate xml metadata of PDF documents. In case of error returns an Exception
//
//============================================================+

final class XMP
{
	private $xref=array();
	private $objects=array();
	private $n=0;
	private $offsets=array();
	private $output="";
	
	public function __construct(Parser $obj)
	{
		if(!empty($obj->getParsedData()[0])){$this->xref=$obj->getParsedData()[0];}else{$this->xref=null;}
		if(!empty($obj->getParsedData()[1])){$this->objects=$obj->getParsedData()[1];}else{$this->objects=null;}
	}
	
	public function serializeObjBody($obj_body)
	{
		$b=array();

		if(is_array($obj_body))
		{
			foreach($obj_body as $k=>$v)
			{
				$this->out($v, $b[$k]);
			}
		}
		
		
		return $b;
	}
	public function out($t, &$b)
	{
		if(empty($t[0]) and $t[0]!==0){throw new \Exception("MISSING_ARGUMENT");}
		if(empty($t[1]) and $t[1]!==0){throw new \Exception("MISSING_ARGUMENT");}
		
		if($t[0]!="numeric" and $t[0]!="objref"){$b[]=$t[0];}
		if($t[0]=="numeric"){$b[]=' ';}
		
		if(is_array($t[1]))
		{
			foreach($t[1] as $t)
			{
				$this->out($t, $b);
			}
		}
		else
		{
			if($t[0]=="objref"){$b[]=' '.str_replace('_', ' ', $t[1])." R";}else{$b[]=$t[1];}
			
		}
	}
	
	public function getXrefArgument($argument)
	{
		if(!array_key_exists("trailer", $this->xref) OR !array_key_exists($argument, $this->xref["trailer"]) OR empty($this->xref["trailer"][$argument])){throw new \Exception("MISSING_ARGUMENT");}
		
		
		return $this->xref["trailer"][$argument];
	}
	
	public function getRootId()
	{
		return $this->getXrefArgument("root");
	}
	
	public function getMetadataObjId($serialized_object_body)
	{
		foreach($serialized_object_body as $t)
		{
			foreach($t as $k=>$v)
			{
				$in=$k;if($k>0){$in=$in-1;}
				if($t[$in]=="Metadata")
				{
					return str_replace(' ', '_', trim(rtrim($v, 'R')));
				}
			}
		}
	}
	
	public function getLastSize()
	{
		return $this->getXrefArgument("size");
	}
	
	public function getLastStartxref()
	{
		if(!array_key_exists("startxref", $this->xref) OR empty($this->xref["startxref"])){throw new \Exception("MISSING_LAST_STARTXREF");}
		
		
		return $this->xref["startxref"];
	}
	
	public function getLength()
	{
		if(!array_key_exists("length", $this->xref) OR empty($this->xref["length"])){throw new \Exception("MISSING_PDF_LENGTH");}
		
		
		return $this->xref["length"];
	}
	
	public function getActualLength()
	{
		return $this->getLength()+strlen($this->output);
	}
	
	public function getNewObjId()
	{
		return $this->getLastSize()+$this->n;
	}
	
	public function newMetadataObj($sign_array)
	{
		$rdf_content=Statics::getXMPSignature($sign_array);
		
		$xmp="<?xpacket begin=\"ď»ż\" id=\"W5M0MpCehiHzreSzNTczkc9d\"?>\n";
		$xmp.="\t<x:xmpmeta xmlns:x=\"adobe:ns:meta/\">\n";
		$xmp.="\t\t<rdf:RDF xmlns:rdf=\"http://www.w3.org/1999/02/22-rdf-syntax-ns#\">\n";
		$xmp.=$rdf_content;
		$xmp.="\t\t</rdf:RDF>\n";
		$xmp.="\t</x:xmpmeta>\n";
		$xmp.="<?xpacket end=\"w\"?>";
		
		$dom=new \DOMDocument();
		$dom->loadXML($xmp);
		if(!$this->xmpSignatureValidate($dom)){throw new \Exception("INVALID_SIGNATURE");}
		
		$obj=$this->getNewObjId()." 0 obj\n";
		$obj.="<</Length ".strlen($xmp)."/Type/Metadata/Subtype/XML>>";
		$obj.="\nstream\n";
		$obj.=$xmp;
		$obj.="\nendstream\n";
		$obj.="endobj\n";
		
		$this->offsets[$this->getNewObjId()]=$this->getActualLength();
		$this->n++;
		$this->output.=$obj;
	}
	
	public function createXref()
	{
		$obj="xref\n";
		foreach($this->offsets as $k=>$v)
		{
			$obj.=$k." 1\n";
			$obj.=sprintf('%010d 00000 n ', $v)."\n";
		}
		$obj.="trailer\n";
		$obj.="<<";
		$obj.="/Info ".explode('_', $this->getXrefArgument("info"))[0]." ".explode('_', $this->getXrefArgument("info"))[1]." R";
		$obj.="/Size ".$this->getNewObjId();
		$obj.="/Root ".explode('_', $this->getRootId())[0]." ".explode('_', $this->getRootId())[1]." R";
		$obj.="/Prev ".$this->getLastStartxref();
		try{$obj.="/ID [<".$this->getXrefArgument("id")[0]."><".$this->getXrefArgument("id")[1].">]";}catch(\Exception $e){}//optional /Catalog parameter
		$obj.=">>\n";
		$obj.="startxref\n";
		$obj.=$this->getActualLength()."\n";
		$obj.="%%EOF\n";
		
		$this->output.=$obj;
	}
	
	public function xmpSignatureValidate(\DOMDocument $dom)
	{
		if($dom->getElementsByTagNameNS(Statics::signNS(), "signatures")->length!=1){throw new \Exception("ERROR_MORE_SIGNATURES_TAG");}
		$node=$dom->getElementsByTagNameNS(Statics::signNS(), "signatures")->item(0);
		if(empty($node)){throw new \Exception("MISSING_SIGNATURES_TAG");}
		
		$dom2=new \DOMDocument();
		$node2=$dom2->importNode($node,true);
		if(empty($node2)){throw new \Exception("MISSING_SIGNATURES_TAG");}
		$dom2->appendChild($node2);
		
		
		return $dom2->schemaValidate(rtrim(Statics::signNS(), '/').".xsd");
	}
	
	
	public function manipulateMetadata($sign_array)
	{
		if(empty($sign_array)){throw new \Exception("SIGNVALUE_NOT_EXSITS");}
		
		//Get catalog, check is existed
		if(empty($this->objects[$this->getRootId()])){throw new \Exception("ROOT_NOT_AVAILABLE");}
		$catalog=$this->serializeObjBody($this->objects[$this->getRootId()]);
		if(empty($catalog)){throw new \Exception("ROOT_NOT_AVAILABLE");}
		
		//Root : Metadata objref exists
		if(!empty($this->getMetadataObjId($catalog)))
		{
			//copy Metadata object
			if(empty($this->objects[$this->getMetadataObjId($catalog)])){throw new \Exception("METADATA_NOT_AVAILABLE");}
			$serialized_metadata=$this->serializeObjBody($this->objects[$this->getMetadataObjId($catalog)]);
			//does metadata object exist?
			if(empty($serialized_metadata)){throw new \Exception("METADATA_NOT_AVAILABLE");}
			
			/*redefine Metadata object START*/
			
			$obj_num=explode('_', $this->getMetadataObjId($catalog))[0];
			$obj=$obj_num." 0 obj\n";
			
			//output original metadata header
			foreach($serialized_metadata[0] as $k=>$v)
			{
				$obj.=$v;
			}
			
			$obj.="\nstream\n";
			
			//Get existed RDF
			$dom=new \DOMDocument();
			if(empty($serialized_metadata[1][1])){throw new \Exception("MISSING_ARGUMENT");}
			if(!$dom->loadXML($serialized_metadata[1][1])){throw new \Exception("ERROR_FAIL_XML_LOAD");}
			//Does RDF exist?
			if($dom->getElementsByTagNameNS("http://www.w3.org/1999/02/22-rdf-syntax-ns#", "RDF")->length!=1){throw new \Exception("RDF_ROOT_UNDEFINED");}
			
			//drop signatures if exists
			if($dom->getElementsByTagNameNS(Statics::signNS(), "signatures")->length>1){throw new \Exception("ERROR_MORE_SIGNATURES_TAG");}
			if($dom->getElementsByTagNameNS(Statics::signNS(), "signatures")->length==1)
			{
				$rdf=$dom->getElementsByTagNameNS("http://www.w3.org/1999/02/22-rdf-syntax-ns#", "RDF")->item(0);
				$sign=$dom->getElementsByTagNameNS(Statics::signNS(), "signatures")->item(0);
				$rdf->removeChild($sign);
			}
				
			//Create XSD nodes by sign_array
			$dom->getElementsByTagNameNS("http://www.w3.org/1999/02/22-rdf-syntax-ns#", "RDF")->item(0)->appendChild($dom->createElementNS(Statics::signNS(), "s:signatures"));
			$i=0;
			foreach($sign_array as $s)
			{
				$dom->getElementsByTagNameNS(Statics::signNS(), "signatures")->item(0)->appendChild($dom->createElementNS(Statics::signNS(), "s:signature"));
				
				$dom->getElementsByTagNameNS(Statics::signNS(), "signature")->item($i)->appendChild($dom->createElementNS(Statics::signNS(), "s:email", $s["email"]));
				$dom->getElementsByTagNameNS(Statics::signNS(), "signature")->item($i)->appendChild($dom->createElementNS(Statics::signNS(), "s:level", $s["level"]));
				$i++;
			}
			
			if(!$dom_data=$dom->saveXML()){throw new \Exception("ERROR_FAIL_XML_SAVE");}
			
			$dom2=new \DOMDocument();
			if(!$dom2->loadXML($dom_data)){throw new \Exception("ERROR_FAIL_XML_LOAD");}
			if(!$this->xmpSignatureValidate($dom2)){throw new \Exception("INVALID_SIGNATURE");}
			
			$obj.=$dom_data;
			
			/*redefine Metadata object END*/
			
			$obj.="\nendstream\n";
			$obj.="endobj\n";
			
			$this->offsets[$obj_num]=$this->getActualLength();
			$this->output.=$obj;
		}
		else
		{
			//Xref : Trailer : Size exists
			if(empty($this->getLastSize())){throw new \Exception("SIZE_NOT_EXISTS");}
			
			$this->newMetadataObj($sign_array);
			
			//add Metadata objref to Root
			$obj_num=explode('_', $this->getRootId())[0];
			$obj=$obj_num." 0 obj\n";
			$root_content="";
			foreach($this->serializeObjBody($this->objects[$this->getRootId()])[0] as $k=>$v)
			{
				$root_content.=$v;
			}
			$obj.="<</Metadata ".($this->getNewObjId()-1)." 0 R".ltrim($root_content, "<<");
			$obj.="\nendobj\n";
			
			$this->offsets[$obj_num]=$this->getActualLength();
			$this->output.=$obj;
		}
		
		$this->createXref();
		
		
		return $this->output;
	}
	
	public function readMetadataSignature()
	{
		$signatures=array();
		
		//Get catalog, check is existed
		if(empty($this->objects[$this->getRootId()])){throw new \Exception("ROOT_NOT_AVAILABLE");}
		$catalog=$this->serializeObjBody($this->objects[$this->getRootId()]);
		if(empty($catalog)){throw new \Exception("ROOT_NOT_AVAILABLE");}
		
		//Root : Metadata objref exists
		if(empty($this->getMetadataObjId($catalog))){throw new \Exception("METADATA_NOT_EXISTS");}
		//Get Metadata object, check is existed
		if(empty($this->objects[$this->getMetadataObjId($catalog)])){throw new \Exception("METADATA_NOT_AVAILABLE");}
		$serialized_metadata=$this->serializeObjBody($this->objects[$this->getMetadataObjId($catalog)]);
		if(empty($serialized_metadata)){throw new \Exception("METADATA_NOT_AVAILABLE");}
			
		//Get existed RDF
		$dom=new \DOMDocument();
		if(!$dom->loadXML($serialized_metadata[1][1])){throw new \Exception("ERROR_FAIL_XML_LOAD");}
		//Does RDF exist?
		if($dom->getElementsByTagNameNS("http://www.w3.org/1999/02/22-rdf-syntax-ns#", "RDF")->length!=1){throw new \Exception("RDF_ROOT_UNDEFINED");}
		//Does signatures exist?
		if($dom->getElementsByTagNameNS(Statics::signNS(), "signatures")->length!=1){throw new \Exception("SIGNATURE_NOT_EXISTS");}
		//Is signatures valid?
		if(!$this->xmpSignatureValidate($dom)){throw new \Exception("INVALID_SIGNATURE");}
		
		//Get signatures:signature nodes
		$signature_array=$dom->getElementsByTagNameNS(Statics::signNS(), "signature");
		//Get values of signature:email, signature:level nodes
		foreach($signature_array as $s_arr)
		{
			$signatures[]=array("email"=>$s_arr->getElementsByTagName("email")->item(0)->nodeValue, "level"=>$s_arr->getElementsByTagName("level")->item(0)->nodeValue);
		}
		
		
		return $signatures;
	}
}
?>