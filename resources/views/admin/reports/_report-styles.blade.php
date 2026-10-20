<style>
    @page { margin: 18mm 14mm 16mm; }
    * { box-sizing: border-box; }
    body { margin: 0; font-family: "DejaVu Sans", Arial, Helvetica, sans-serif; font-size: 10.5px; line-height: 1.45; color: #1E293B; }
    table { width: 100%; border-collapse: collapse; }

    /* Letterhead */
    .brand { width: 100%; border-bottom: 3px solid #0F4CDB; margin-bottom: 14px; }
    .brand td { padding: 0 0 10px; vertical-align: bottom; }
    .brand .college { font-size: 17px; font-weight: bold; color: #0F4CDB; letter-spacing: .3px; }
    .brand .sub { font-size: 10px; color: #64748B; margin-top: 2px; }
    .brand .title { text-align: right; }
    .brand .title b { display: block; font-size: 13px; color: #1E293B; text-transform: uppercase; letter-spacing: .8px; }
    .brand .title span { font-size: 9.5px; color: #64748B; }

    /* Summary cards (table cells instead of flex so it renders everywhere) */
    .stats { width: 100%; border-collapse: separate; border-spacing: 8px 0; margin: 0 -8px 16px; }
    .stats td { width: 25%; padding: 10px 8px; text-align: center; background: #F1F5FF; border: 1px solid #DBE4FF; border-top: 3px solid #0F4CDB; }
    .stats td.ok   { border-top-color: #16A34A; background: #F0FDF4; border-color: #BBF7D0; }
    .stats td.warn { border-top-color: #D97706; background: #FFFBEB; border-color: #FDE68A; }
    .stats td.bad  { border-top-color: #DC2626; background: #FEF2F2; border-color: #FECACA; }
    .stats .num { display: block; font-size: 19px; font-weight: bold; color: #0F4CDB; }
    .stats .ok .num   { color: #15803D; }
    .stats .warn .num { color: #B45309; }
    .stats .bad .num  { color: #B91C1C; }
    .stats .lbl { display: block; margin-top: 2px; font-size: 8.5px; letter-spacing: .5px; text-transform: uppercase; color: #64748B; }

    /* Data table */
    table.data { border: 1px solid #E2E8F0; }
    table.data thead { display: table-header-group; }
    table.data thead th {
        background: #0F4CDB; color: #fff; padding: 8px 8px; text-align: left;
        font-size: 8.5px; letter-spacing: .6px; text-transform: uppercase; white-space: nowrap;
    }
    table.data tbody td { padding: 7px 8px; border-bottom: 1px solid #E2E8F0; vertical-align: middle; }
    table.data tbody tr:nth-child(even) td { background: #F8FAFC; }
    table.data tr { page-break-inside: avoid; }
    .num-col { text-align: right; white-space: nowrap; }
    .center  { text-align: center; }
    .muted   { color: #64748B; }
    .strong  { font-weight: bold; }
    .mono    { font-family: "DejaVu Sans Mono", monospace; font-size: 9.5px; }

    .badge { display: inline-block; padding: 2px 8px; border-radius: 9px; font-size: 8.5px; font-weight: bold; white-space: nowrap; }
    .badge-success { background: #DCFCE7; color: #15803D; }
    .badge-warning { background: #FEF3C7; color: #B45309; }
    .badge-danger  { background: #FEE2E2; color: #B91C1C; }
    .badge-neutral { background: #E2E8F0; color: #475569; }

    table.data tr.total-row td { background: #0F4CDB !important; color: #fff; font-weight: bold; font-size: 11px; border: 0; padding: 10px 8px; }
    table.data tr.total-row td.amt { color: #FFC72C; font-size: 12.5px; }
    .empty { text-align: center; padding: 22px 0 !important; color: #94A3B8; }

    .footer { margin-top: 18px; padding-top: 8px; border-top: 1px solid #E2E8F0; text-align: center; font-size: 8.5px; color: #94A3B8; }
</style>
