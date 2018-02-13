<?php

/**
 * @file
 * Functionality to add compound ordering.
 */

/**
 * Adds a constituent ordering to an object.
 *
 * @param AbstractObject $object
 *   The constituent object.
 * @param string $parent
 *   The PID of the parent to use to determine constituent ordering.
 */
function islandora_spreadsheet_ingest_compounds_order_constituent(AbstractObject $object, $parent) {
  $escaped_parent = str_replace(':', '_', $parent);
  // It's possible for this object to have a constituent ordering applied via
  // other means; let's not step on anyone's toes.
  if (!$object->relationships->get(ISLANDORA_RELS_EXT_URI, "isSequenceNumberOf$escaped_parent")) {
    module_load_include('inc', 'islandora_compound_object', 'includes/manage.form');
    $children = islandora_compound_object_get_parts($parent, TRUE);
    $insert_seq = islandora_compound_object_get_insert_sequence_number($children);
    $object->relationships->add(ISLANDORA_RELS_EXT_URI, "isSequenceNumberOf$escaped_parent", $insert_seq, RELS_TYPE_PLAIN_LITERAL);
  }
}
