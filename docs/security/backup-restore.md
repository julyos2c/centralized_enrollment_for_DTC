# Backup recovery procedure

The web interface does not accept backup uploads or execute SQL restore files.
Use this procedure for recovery:

1. Open an incident/change record and identify the required recovery point.
2. Obtain the backup from its restricted backup location. Do not use a file
   submitted through the application or treat its filename, extension, MIME
   type, or a hash supplied with that file as proof of origin.
3. Verify the artifact against a digital signature or integrity manifest held
   separately in the restricted backup system. Stop if provenance cannot be
   verified.
4. Restore into an isolated staging database using the organization's approved
   database administration tooling and credentials kept outside command-line
   arguments and application logs.
5. Validate schema, migration state, and application behavior in staging. Take
   and independently verify a current recovery copy of the target database.
6. Schedule an authorized maintenance window, restrict application writes,
   repeat the verified restore using the approved DBA process, and validate
   the recovered service before reopening access.
7. Record the recovery point, verification result, operator, and outcome in the
   incident/change record. Do not attach database contents or credentials.

The application's generated SQL download is an export convenience; it is not
signed or provenance-verified by the application and therefore must not be
treated as a trusted restore artifact until the restricted backup system has
independently authenticated it. Configure an independently managed signed
backup process before relying on automated recovery.
