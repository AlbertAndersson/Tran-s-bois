# Nästa uppdrag – P18B hostacceptans

P18A är levererad via PR40; P18 som helhet är IN PROGRESS. P18B startas när Albert
beställer den. Läs CURRENT_STATUS.md, P18-RELEASE-ACCEPTANCE.md och den aktuella
SharePoint-utvecklingskön först. Kontrollera aktuell main, öppna PR:er och faktiskt
installerad app-/workflow-SHA; lokalt opushat Codex-arbete måste delas via GitHub
för att kunna jämföras. Bevara aktuell branchhistorik och andra trådars ändringar.

Använd P18B:s befintliga plan för privat backup/rollback, isolerad production-DB,
privata runtimevägar/rättigheter, stängd launch, MFA/revoke, probes/drift/loggning,
retention-dry-run och hostrestore. Köp provas enbart i separat syntetisk testinstans
med egen DB; production ska inte växlas till mock eller få fabricerade beslut.

Bevara stagingens årsgräns 20, äldre syntetiska order, shirt-only matchtröja och
supporterpreview utan köp/P7-datumspärr. P8-readinessworkflow är en historisk
källpinne; den ska inte användas för att uppgradera dagens skyddade P9-staging.
P19-underlaget är förberett i P19-DECISIONS-DRAFT.md, men inte skickat eller beslutat.

Registrera SHA, miljö, datum, faktisk kontroll och begränsning för varje hostbevis.
Samlad P18-RC/tag och PRODUCTION READY-status får vänta tills P18A+B är accepterade.
P20, Stripe live, extern mail, skarp gallring och launch kräver sina uttryckliga
beslut och startas inte automatiskt. Ingen ny extern kostnad ingår.
