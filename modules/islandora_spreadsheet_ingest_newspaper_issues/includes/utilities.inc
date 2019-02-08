<?php

/**
 * @file
 * Utility functions.
 */

/**
 * Assign an object a sequence number.
 *
 * @param AbstractObject $object
 *   The object to receive the sequence number.
 */
function _islandora_spreadsheet_ingest_newspaper_issues_sequence(AbstractObject $object) {
  module_load_include('inc', 'islandora_newspaper', 'includes/utilities');
  if (islandora_newspaper_get_current_sequence($object)) {
    return FALSE;
  }

  $parent_pid = islandora_newspaper_get_newspaper($object);
  if (!$parent_pid) {
    throw new Exception(t('Issue (@pid) missing relationship to parent.', array(
      '@pid' => $object->id,
    )));
  }
  $parent = islandora_object_load($parent_pid);
  if (!$parent) {
    throw new Exception(t('Failed to load object (@pid) related to issue (@issue).', array(
      '@pid' => $parent_pid,
      '@issue' => $object->id,
    )));
  }

  // XXX: Specifically using Sparql for consistency... Solr lagging would result
  // in the same sequence number being assigned to multiple issues.
  $issues = islandora_newspaper_get_issues_sparql($parent);

  $sequence_numbers = array_column($issues, 'sequence');
  $last_sequence = empty($sequence_numbers) ? 0 : max($sequence_numbers);
  $object->relationships->add(ISLANDORA_RELS_EXT_URI, 'isSequenceNumber', $last_sequence + 1, TRUE);
}
