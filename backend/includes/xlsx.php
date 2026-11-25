<?php
// تولید xlsx ساده (بدون کتابخانه) با ZipArchive (یا جایگزین SimpleZip اگر افزونه نبود)
require_once __DIR__.'/zipwriter.php';
function xlsx_col(int $i):string{$s='';for($i++;$i>0;$i=intdiv($i-1,26))$s=chr(65+($i-1)%26).$s;return $s;}
function xlsx_output(array $rows,string $filename):void{
 $x='<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetViews><sheetView rightToLeft="1" workbookViewId="0"/></sheetViews><sheetData>';
 foreach($rows as $r=>$row){$x.='<row r="'.($r+1).'">';foreach(array_values($row) as $c=>$v)$x.='<c r="'.xlsx_col($c).($r+1).'" t="inlineStr"><is><t xml:space="preserve">'.htmlspecialchars((string)$v,ENT_XML1|ENT_SUBSTITUTE).'</t></is></c>';$x.='</row>';}
 $x.='</sheetData></worksheet>';$tmp=tempnam(sys_get_temp_dir(),'xl');$z=make_zip($tmp);
 $z->addFromString('[Content_Types].xml','<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>');
 $z->addFromString('_rels/.rels','<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
 $z->addFromString('xl/workbook.xml','<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Registrations" sheetId="1" r:id="rId1"/></sheets></workbook>');
 $z->addFromString('xl/_rels/workbook.xml.rels','<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>');
 $z->addFromString('xl/worksheets/sheet1.xml',$x);$z->close();
 header_remove('Content-Type');header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');header("Content-Disposition: attachment; filename=\"$filename\"");header('Content-Length: '.filesize($tmp));readfile($tmp);unlink($tmp);}
