import { useEffect, useState } from "react"

import { DashboardShell, ResponsiveGrid, Section } from "@/components/layout"
import {
  Badge,
  Button,
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
  StatCard,
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
  buttonClasses,
} from "@/components/ui"

type EmployeeInfo = {
  employeeId: number
  name: string
  status: string
  hourlyWage: number
  departmentId: number | null
  departmentName: string
  departmentType: string
  departmentLabel: string
  entranceFeeTickets: number
  capacity: number
  operatingStatus: string
  reserveBalance: number
  generatedBalance: number
}

type Shift = {
  shiftId: number
  startTime: string
  endTime: string | null
  entryType: string
  durationMinutes: number
  formattedStart: string
  formattedEnd: string
  formattedHours: string
}

type SickRequest = {
  sickRequestId: number
  requestDate: string
  status: string
  notes: string
  requestedAt: string
  reviewedAt: string
  reviewNotes: string
}

type Member = {
  attendeeId: number
  name: string
  membershipCode: string
  walletBalance: number
  email: string | null
  phone: string | null
  verifiedAt: string | null
  verifiedByEmployeeName: string | null
}

type ActiveSession = {
  sessionId: number
  displayName: string
  admissionMode: string
  entranceFeeTickets: number
  openedAt: string
  attendeeName: string
  sessionWalletBalance: number
}

type RecentSession = {
  sessionId: number
  displayName: string
  admissionMode: string
  payoutTickets: number
  closedAt: string
  attendeeName: string
  sessionWalletBalance: number
}

type GiftShopItem = {
  itemId: number
  name: string
  ticketPrice: number
  costPrice?: number
  stock: number
  status?: string
  category?: string
  description?: string
}

type WalletSource = {
  sourceToken: string
  sourceLabel: string
  balance: number
}

type RecentRedemption = {
  redemptionId: number
  itemName: string
  quantity: number
  totalTickets: number
  attendeeName: string
  sessionName: string
  redeemedAt: string
}

type ClaimCandidate = {
  sessionId: number
  displayName: string
  departmentName: string
  closedAt: string
  walletBalance: number
}

export type EmployeeDashboardProps = {
  currentUser: {
    name: string
    role: string
  }
  flash: string | null
  error: string | null
  employee: EmployeeInfo | null
  summary: {
    todayHours: number
    weekHours: number
    approvedSickDaysThisWeek: number
    weekTargetHours: number
    totalHours: number
  }
  openLiveShift: {
    shiftId: number
    startTime: string
    formattedStart: string
  } | null
  shifts: Shift[]
  sickRequests: SickRequest[]
  members: Member[]
  activeSessions: ActiveSession[]
  recentSessions: RecentSession[]
  giftShopBudgetAvailable: number
  giftShopInventorySpendTotal: number
  giftShopItems: GiftShopItem[]
  redeemableGiftShopItems: GiftShopItem[]
  walletSources: WalletSource[]
  recentRedemptions: RecentRedemption[]
  claimCandidates: ClaimCandidate[]
}

const inputClass =
  "h-10 rounded-base border-2 border-border bg-secondary-background px-3 font-sans text-sm font-base focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
const textInputClass =
  "min-h-20 rounded-base border-2 border-border bg-secondary-background px-3 py-2 font-sans text-sm font-base focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"

function labelize(value: string) {
  return value.replace(/_/g, " ").replace(/\b\w/g, (letter) => letter.toUpperCase())
}

function number(value: number) {
  return value.toLocaleString()
}

function quarterHoursSince(value: string) {
  const startedAt = new Date(value).getTime()
  if (Number.isNaN(startedAt)) {
    return "0.00"
  }

  const hours = Math.max((Date.now() - startedAt) / 36e5, 0)
  return (Math.floor(hours * 4) / 4).toFixed(2)
}

function Field({
  children,
  label,
}: {
  children: React.ReactNode
  label: string
}) {
  return (
    <label className="grid gap-1 font-heading text-sm">
      {label}
      {children}
    </label>
  )
}

function StatusBadge({ status }: { status: string }) {
  const variant = status === "approved" || status === "active" ? "success" : status === "denied" || status === "terminated" ? "danger" : "warning"

  return <Badge variant={variant}>{labelize(status || "unknown")}</Badge>
}

export function EmployeeDashboard({
  currentUser,
  flash,
  error,
  employee,
  summary,
  openLiveShift,
  shifts,
  sickRequests,
  members,
  activeSessions,
  recentSessions,
  giftShopBudgetAvailable,
  giftShopInventorySpendTotal,
  giftShopItems,
  redeemableGiftShopItems,
  walletSources,
  recentRedemptions,
  claimCandidates,
}: EmployeeDashboardProps) {
  const [showShifts, setShowShifts] = useState(false)
  const [activeMode, setActiveMode] = useState<"employee" | "department">("employee")
  const [liveShiftHours, setLiveShiftHours] = useState(
    openLiveShift ? quarterHoursSince(openLiveShift.startTime) : "0.00",
  )

  useEffect(() => {
    document.documentElement.classList.add("ams-employee-theme")

    return () => {
      document.documentElement.classList.remove("ams-employee-theme")
    }
  }, [])

  useEffect(() => {
    if (!openLiveShift) {
      setLiveShiftHours("0.00")
      return
    }

    const updateHours = () => setLiveShiftHours(quarterHoursSince(openLiveShift.startTime))
    updateHours()
    const timer = window.setInterval(updateHours, 60_000)

    return () => window.clearInterval(timer)
  }, [openLiveShift])

  if (!employee) {
    return (
      <DashboardShell
        actions={<a className={buttonClasses()} href="logout.php">Logout</a>}
        description="Your employee profile has not been set up yet. Please contact HR."
        roleLabel={`Logged in as ${currentUser.name}`}
        title="Employee Dashboard"
      >
        <Card>
          <CardTitle>No employee profile found.</CardTitle>
        </Card>
      </DashboardShell>
    )
  }

  return (
    <DashboardShell
      actions={
        <>
          <a className={buttonClasses({ variant: "neutral" })} href="index.php">
            Back Home
          </a>
          <a className={buttonClasses({ className: "hover:bg-danger" })} href="logout.php">
            Logout
          </a>
        </>
      }
      description="Track your shift, sick requests, and department work."
        sidecar={
          <Card className="w-full gap-3 p-4">
            <div className="flex flex-wrap items-center justify-between gap-2">
              <span className="font-heading text-sm">
                {openLiveShift ? `${liveShiftHours} hrs` : "Not clocked in"}
              </span>
              <Badge variant={employee.operatingStatus === "active" ? "success" : "danger"}>
                Department {labelize(employee.operatingStatus)}
              </Badge>
            </div>
            <form action="employee_dashboard.php" method="post">
              <input name="action" type="hidden" value={openLiveShift ? "clock_out_shift" : "clock_in_shift"} />
              <Button className="w-full" size="sm" type="submit">
                {openLiveShift ? "Clock Out" : "Clock In"}
              </Button>
            </form>
          </Card>
        }
      roleLabel={`Logged in as ${currentUser.name}`}
      title="Employee Dashboard"
    >
      {flash ? (
        <Card className="bg-success">
          <CardTitle>{flash}</CardTitle>
        </Card>
      ) : null}
      {error ? (
        <Card className="bg-danger">
          <CardTitle>{error}</CardTitle>
        </Card>
      ) : null}

      <div className="mx-auto flex w-full max-w-xl gap-3 rounded-base border-2 border-border bg-secondary-background p-3 shadow-shadow">
        <Button
          onClick={() => setActiveMode("employee")}
          variant={activeMode === "employee" ? "default" : "neutral"}
          className="flex-1 data-[active=true]:bg-success"
          data-active={activeMode === "employee"}
        >
          Employee
        </Button>
        <Button
          className="flex-1 data-[active=true]:bg-success"
          data-active={activeMode === "department"}
          onClick={() => setActiveMode("department")}
          variant={activeMode === "department" ? "default" : "neutral"}
        >
          Department
        </Button>
      </div>

      {activeMode === "employee" ? (
      <Section title="Employee">
        <ResponsiveGrid>
          <StatCard detail="Hourly wage" label="Wage" value={`$${employee.hourlyWage.toFixed(2)}`} />
          <StatCard detail="Assigned department" label="Department" value={employee.departmentName} />
          <StatCard detail="Employee status" label="Status" value={labelize(employee.status)} />
          <StatCard detail="Today" label="Daily Hours" value={summary.todayHours.toFixed(2)} />
          <StatCard detail={`Target ${summary.weekTargetHours} hrs incl. sick days`} label="Weekly Hours" value={`${summary.weekHours.toFixed(2)} / ${summary.weekTargetHours}`} />
          <StatCard detail="All logged time" label="Total Hours" value={summary.totalHours.toFixed(2)} />
        </ResponsiveGrid>

        <Card>
          <CardHeader>
            <CardTitle>Manual Shift Entry</CardTitle>
            <CardDescription>Add a completed shift by choosing start and end times.</CardDescription>
          </CardHeader>
          <CardContent>
            <form action="employee_dashboard.php" className="grid gap-3" method="post">
              <input name="action" type="hidden" value="add_manual_shift" />
              <Field label="Manual Start">
                <input className={inputClass} name="start_time" required type="datetime-local" />
              </Field>
              <Field label="Manual End">
                <input className={inputClass} name="end_time" required type="datetime-local" />
              </Field>
              <Button type="submit" variant="neutral">Add Manual Shift</Button>
            </form>
          </CardContent>
        </Card>

        <Card>
          <button
            aria-expanded={showShifts}
            className="flex w-full items-center justify-between gap-4 border-0 bg-transparent p-0 text-left"
            onClick={() => setShowShifts((value) => !value)}
            type="button"
          >
            <span>
              <CardTitle>Previous Shifts</CardTitle>
              <CardDescription className="mt-2">{shifts.length} shift(s) logged.</CardDescription>
            </span>
            <Badge>{showShifts ? "Collapse" : "Expand"}</Badge>
          </button>
          {showShifts ? (
            <CardContent className="mt-5">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Start</TableHead>
                    <TableHead>End</TableHead>
                    <TableHead>Type</TableHead>
                    <TableHead>Hours</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {shifts.map((shift) => (
                    <TableRow key={shift.shiftId}>
                      <TableCell>{shift.formattedStart}</TableCell>
                      <TableCell>{shift.formattedEnd}</TableCell>
                      <TableCell>{labelize(shift.entryType)}</TableCell>
                      <TableCell>{shift.formattedHours}</TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </CardContent>
          ) : null}
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Call In Sick</CardTitle>
            <CardDescription>Choose a date. HR will approve or deny the request.</CardDescription>
          </CardHeader>
          <CardContent className="grid gap-5 lg:grid-cols-[0.8fr_1.2fr]">
            <form action="employee_dashboard.php" className="grid gap-3" method="post">
              <input name="action" type="hidden" value="request_sick_day" />
              <Field label="Sick Date">
                <input className={inputClass} name="request_date" required type="date" />
              </Field>
              <Field label="Notes">
                <textarea className={textInputClass} name="notes" />
              </Field>
              <Button type="submit">Send Sick Request</Button>
            </form>
            <div>
              {sickRequests.length === 0 ? (
                <p className="m-0 text-muted-foreground">No sick requests submitted yet.</p>
              ) : (
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>Date</TableHead>
                      <TableHead>Status</TableHead>
                      <TableHead>Notes</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {sickRequests.map((request) => (
                      <TableRow key={request.sickRequestId}>
                        <TableCell>{request.requestDate}</TableCell>
                        <TableCell><StatusBadge status={request.status} /></TableCell>
                        <TableCell>{request.notes || request.reviewNotes}</TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              )}
            </div>
          </CardContent>
        </Card>
      </Section>
      ) : null}

      {activeMode === "department" ? (
      <>
      <Section title="Department">
        <ResponsiveGrid>
          <StatCard detail="Department type" label="Type" value={employee.departmentLabel} />
          <StatCard detail="Operating status" label="Status" value={labelize(employee.operatingStatus)} />
          {employee.departmentType === "gift_shop" ? (
            <>
              <StatCard detail="Play-area payouts and stocking debit this pool" label="Operating budget" value={number(giftShopBudgetAvailable)} />
              <StatCard detail="Recorded in the ticket ledger" label="Inventory procurement" value={number(giftShopInventorySpendTotal)} />
            </>
          ) : (
            <StatCard detail="Legacy per-department reserve (unused for payouts)" label="Reserve" value={number(employee.reserveBalance)} />
          )}
          <StatCard detail="Generated tickets" label="Generated" value={number(employee.generatedBalance)} />
          {employee.departmentType === "play_area" ? (
            <>
              <StatCard detail="Ticket entry cost" label="Entrance Fee" value={number(employee.entranceFeeTickets)} />
              <StatCard detail="Play-area capacity" label="Capacity" value={number(employee.capacity)} />
            </>
          ) : null}
        </ResponsiveGrid>
      </Section>

      {employee.departmentType === "play_area" ? (
        <PlayAreaPanel activeSessions={activeSessions} members={members} recentSessions={recentSessions} />
      ) : null}
      {employee.departmentType === "gift_shop" ? (
        <GiftShopPanel
          budget={giftShopBudgetAvailable}
          inventorySpend={giftShopInventorySpendTotal}
          giftShopItems={giftShopItems}
          recentRedemptions={recentRedemptions}
          redeemableGiftShopItems={redeemableGiftShopItems}
          walletSources={walletSources}
        />
      ) : null}
      {employee.departmentType === "customer_support" ? (
        <CustomerSupportPanel claimCandidates={claimCandidates} members={members} />
      ) : null}
      </>
      ) : null}
    </DashboardShell>
  )
}

function PlayAreaPanel({
  activeSessions,
  members,
  recentSessions,
}: {
  activeSessions: ActiveSession[]
  members: Member[]
  recentSessions: RecentSession[]
}) {
  return (
    <Section title="Play Area Operations">
      <Card>
        <CardHeader>
          <CardTitle>Open Attendee Session</CardTitle>
        </CardHeader>
        <CardContent>
          <form action="employee_dashboard.php" className="grid gap-3 md:grid-cols-2" method="post">
            <input name="action" type="hidden" value="open_session" />
            <Field label="Display Name">
              <input className={inputClass} name="display_name" required type="text" />
            </Field>
            <Field label="Admission Mode">
              <select className={inputClass} name="admission_mode" required>
                <option value="walk_in">Walk-In</option>
                <option value="member_wallet">Member Wallet</option>
                <option value="manual_override">Manual Override</option>
              </select>
            </Field>
            <Field label="Member">
              <select className={inputClass} name="attendee_id">
                <option value="0">No member selected</option>
                {members.map((member) => (
                  <option key={member.attendeeId} value={member.attendeeId}>
                    {member.name} ({member.membershipCode || "no-code"}, balance {member.walletBalance})
                  </option>
                ))}
              </select>
            </Field>
            <Field label="Notes">
              <input className={inputClass} maxLength={255} name="notes" type="text" />
            </Field>
            <Button type="submit">Open Session</Button>
          </form>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>Active Attendees</CardTitle>
        </CardHeader>
        <CardContent>
          {activeSessions.length === 0 ? (
            <p className="m-0 text-muted-foreground">No active attendee sessions in this department.</p>
          ) : (
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Name</TableHead>
                  <TableHead>Member</TableHead>
                  <TableHead>Mode</TableHead>
                  <TableHead>Opened</TableHead>
                  <TableHead>Close</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {activeSessions.map((session) => (
                  <TableRow key={session.sessionId}>
                    <TableCell className="font-heading">{session.displayName}</TableCell>
                    <TableCell>{session.attendeeName}</TableCell>
                    <TableCell>{labelize(session.admissionMode)}</TableCell>
                    <TableCell>{session.openedAt}</TableCell>
                    <TableCell>
                      <form action="employee_dashboard.php" className="grid gap-2" method="post">
                        <input name="action" type="hidden" value="close_session" />
                        <input name="session_id" type="hidden" value={session.sessionId} />
                        <input className={inputClass} min={0} name="payout_tickets" placeholder="Payout" required type="number" />
                        <input className={inputClass} maxLength={255} name="notes" placeholder="Notes" type="text" />
                        <Button size="sm" type="submit">Close</Button>
                      </form>
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          )}
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>Recent Closed Sessions</CardTitle>
        </CardHeader>
        <CardContent>
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Name</TableHead>
                <TableHead>Mode</TableHead>
                <TableHead>Payout</TableHead>
                <TableHead>Closed</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {recentSessions.map((session) => (
                <TableRow key={session.sessionId}>
                  <TableCell>{session.displayName}</TableCell>
                  <TableCell>{labelize(session.admissionMode)}</TableCell>
                  <TableCell>{session.payoutTickets}</TableCell>
                  <TableCell>{session.closedAt}</TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </CardContent>
      </Card>
    </Section>
  )
}

function GiftShopPanel({
  budget,
  inventorySpend,
  giftShopItems,
  redeemableGiftShopItems,
  walletSources,
  recentRedemptions,
}: {
  budget: number
  inventorySpend: number
  giftShopItems: GiftShopItem[]
  redeemableGiftShopItems: GiftShopItem[]
  walletSources: WalletSource[]
  recentRedemptions: RecentRedemption[]
}) {
  return (
    <Section title="Gift Shop Operations">
      <ResponsiveGrid>
        <StatCard detail="Owner investment, admissions, transfers; debited by payouts and stocking" label="Operating budget" value={number(budget)} />
        <StatCard detail="Cumulative procurement recorded in ledger" label="Inventory ledger" value={number(inventorySpend)} />
      </ResponsiveGrid>
      <Card>
        <CardHeader>
          <CardTitle>Add Catalog Item</CardTitle>
        </CardHeader>
        <CardContent>
          <form action="employee_dashboard.php" className="grid gap-3 md:grid-cols-2" method="post">
            <input name="action" type="hidden" value="create_item" />
            <Field label="Name"><input className={inputClass} name="name" required type="text" /></Field>
            <Field label="Ticket Price"><input className={inputClass} max={1000} min={10} name="ticket_price" required type="number" /></Field>
            <Field label="Unit cost to stock (tickets per unit, integers)"><input className={inputClass} min={0} name="cost_price" required step={1} type="number" /></Field>
            <Field label="Stock"><input className={inputClass} min={0} name="stock" required type="number" /></Field>
            <Field label="Category"><input className={inputClass} name="category" type="text" /></Field>
            <Field label="Description"><input className={inputClass} maxLength={255} name="description" type="text" /></Field>
            <Button type="submit">Add Gift Shop Item</Button>
          </form>
        </CardContent>
      </Card>

      <Card>
        <CardHeader><CardTitle>Catalog</CardTitle></CardHeader>
        <CardContent>
          <Table>
            <TableHeader>
              <TableRow><TableHead>Name</TableHead><TableHead>Price</TableHead><TableHead>Stock</TableHead><TableHead>Status</TableHead></TableRow>
            </TableHeader>
            <TableBody>
              {giftShopItems.map((item) => (
                <TableRow key={item.itemId}>
                  <TableCell>{item.name}</TableCell>
                  <TableCell>{item.ticketPrice}</TableCell>
                  <TableCell>{item.stock}</TableCell>
                  <TableCell>{item.status}</TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </CardContent>
      </Card>

      <Card>
        <CardHeader><CardTitle>Redeem Item</CardTitle></CardHeader>
        <CardContent>
          <form action="employee_dashboard.php" className="grid gap-3 md:grid-cols-2" method="post">
            <input name="action" type="hidden" value="redeem_item" />
            <Field label="Item">
              <select className={inputClass} name="item_id" required>
                {redeemableGiftShopItems.map((item) => (
                  <option key={item.itemId} value={item.itemId}>{item.name} - {item.ticketPrice} tickets (stock {item.stock})</option>
                ))}
              </select>
            </Field>
            <Field label="Quantity"><input className={inputClass} min={1} name="quantity" required type="number" /></Field>
            <Field label="Ticket Source">
              <select className={inputClass} name="source_token" required>
                {walletSources.map((source) => (
                  <option key={source.sourceToken} value={source.sourceToken}>{source.sourceLabel} - balance {source.balance}</option>
                ))}
              </select>
            </Field>
            <Field label="Notes"><input className={inputClass} maxLength={255} name="notes" type="text" /></Field>
            <Button type="submit">Redeem Item</Button>
          </form>
        </CardContent>
      </Card>

      <Card>
        <CardHeader><CardTitle>Recent Redemptions</CardTitle></CardHeader>
        <CardContent>
          <Table>
            <TableHeader>
              <TableRow><TableHead>Item</TableHead><TableHead>Quantity</TableHead><TableHead>Total</TableHead><TableHead>Redeemed</TableHead></TableRow>
            </TableHeader>
            <TableBody>
              {recentRedemptions.map((redemption) => (
                <TableRow key={redemption.redemptionId}>
                  <TableCell>{redemption.itemName}</TableCell>
                  <TableCell>{redemption.quantity}</TableCell>
                  <TableCell>{redemption.totalTickets}</TableCell>
                  <TableCell>{redemption.redeemedAt}</TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </CardContent>
      </Card>
    </Section>
  )
}

function CustomerSupportPanel({
  claimCandidates,
  members,
}: {
  claimCandidates: ClaimCandidate[]
  members: Member[]
}) {
  return (
    <>
      <Section title="Members">
        <Card>
          <CardHeader>
            <CardTitle>All members</CardTitle>
            <CardDescription>
              Verified members with contact info, wallet balance, and verification details.
            </CardDescription>
          </CardHeader>
          <CardContent>
            {members.length === 0 ? (
              <p className="m-0 text-muted-foreground">No members on file yet.</p>
            ) : (
              <div className="overflow-x-auto">
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>Name</TableHead>
                      <TableHead>Code</TableHead>
                      <TableHead>Email</TableHead>
                      <TableHead>Phone</TableHead>
                      <TableHead className="text-right">Wallet</TableHead>
                      <TableHead>Verified</TableHead>
                      <TableHead>Verified by</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {members.map((member) => (
                      <TableRow key={member.attendeeId}>
                        <TableCell className="font-heading">{member.name}</TableCell>
                        <TableCell className="whitespace-nowrap">
                          {member.membershipCode || "—"}
                        </TableCell>
                        <TableCell>{member.email?.trim() ? member.email : "—"}</TableCell>
                        <TableCell>{member.phone?.trim() ? member.phone : "—"}</TableCell>
                        <TableCell className="text-right font-heading">
                          {number(member.walletBalance)}
                        </TableCell>
                        <TableCell className="whitespace-nowrap text-sm">
                          {member.verifiedAt
                            ? new Date(member.verifiedAt).toLocaleString()
                            : "—"}
                        </TableCell>
                        <TableCell>{member.verifiedByEmployeeName ?? "—"}</TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              </div>
            )}
          </CardContent>
        </Card>
      </Section>

      <Section title="Customer Support Claims">
        <Card>
          <CardContent>
          {claimCandidates.length === 0 ? (
            <p className="m-0 text-muted-foreground">No claimable sessions are waiting for verification.</p>
          ) : (
            <Table>
              <TableHeader>
                <TableRow><TableHead>Session</TableHead><TableHead>Department</TableHead><TableHead>Claimable</TableHead><TableHead>Create Member</TableHead></TableRow>
              </TableHeader>
              <TableBody>
                {claimCandidates.map((candidate) => (
                  <TableRow key={candidate.sessionId}>
                    <TableCell>{candidate.displayName}</TableCell>
                    <TableCell>{candidate.departmentName}</TableCell>
                    <TableCell>{candidate.walletBalance}</TableCell>
                    <TableCell>
                      <form action="employee_dashboard.php" className="grid gap-2" method="post">
                        <input name="action" type="hidden" value="claim_member" />
                        <input name="session_id" type="hidden" value={candidate.sessionId} />
                        <input className={inputClass} name="name" placeholder="Name" required type="text" />
                        <input className={inputClass} name="email" placeholder="Email" type="email" />
                        <input className={inputClass} name="membership_code" placeholder="Membership code" required type="text" />
                        <Button size="sm" type="submit">Verify Claim</Button>
                      </form>
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          )}
        </CardContent>
      </Card>
    </Section>
    </>
  )
}
