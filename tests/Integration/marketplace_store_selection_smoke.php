<?php
require (dirname(__DIR__, 2) . '/app/Modules/Marketplace/Services/MarketplaceStoreSelection.php');
function selectionCheck($ok){if(!$ok)throw new RuntimeException('Store selection failed');}
selectionCheck(MarketplaceStoreSelection::resolve([],'','foreign')==='');
selectionCheck(MarketplaceStoreSelection::resolve([['id'=>'A']],'','foreign')==='A');
selectionCheck(MarketplaceStoreSelection::resolve([['id'=>'A'],['id'=>'B']],'','foreign')==='');
selectionCheck(MarketplaceStoreSelection::resolve([['id'=>'A'],['id'=>'B']],'','B')==='B');
selectionCheck(MarketplaceStoreSelection::resolve([['id'=>'A'],['id'=>'B']],'A','B')==='A');
$blocked=false;try{MarketplaceStoreSelection::resolve([['id'=>'A']],'foreign','A');}catch(DomainException $e){$blocked=true;}selectionCheck($blocked);
echo "PASS: no-store, single-store, multiple-store, remembered-store revalidation and foreign-store rejection.\n";
