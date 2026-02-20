-- Backfill manual_export_audits.from_bought_id and to_bought_id for existing exports.
-- from_bought_id: first successful manuals_bought row that is still pending grant as at export time.
-- to_bought_id: latest successful manuals_bought row as at export time.

UPDATE manual_export_audits AS a
SET
  a.from_bought_id = (
    SELECT MIN(mb.id)
    FROM manuals_bought AS mb
    WHERE mb.manual_id = a.manual_id
      AND mb.status = 'successful'
      AND (mb.grant_status IS NULL OR mb.grant_status = 0)
      AND mb.created_at <= a.downloaded_at
  ),
  a.to_bought_id = (
    SELECT MAX(mb.id)
    FROM manuals_bought AS mb
    WHERE mb.manual_id = a.manual_id
      AND mb.status = 'successful'
      AND mb.created_at <= a.downloaded_at
  )
WHERE a.from_bought_id IS NULL
   OR a.to_bought_id IS NULL;
