import { useEffect, useMemo, useState } from "react"
import { Minus, Pencil, Plus } from "lucide-react"
import {
  CartesianGrid,
  Line,
  LineChart,
  Tooltip,
  XAxis,
  YAxis,
} from "recharts"

import { DashboardShell, ResponsiveGrid, Section } from "@/components/layout"
import {
  Badge,
  Button,
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
  ChartContainer,
  ChartTooltip,
  ChartTooltipPanel,
  StatCard,
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
  buttonClasses,
  type ChartConfig,
  useChartConfig,
} from "@/components/ui"

type OwnerSummary = {
  credits: number
  circulation: number
  giftShopRevenue: number
  departmentReserve: number
  departmentGenerated: number
  activeAttendees: number
}

type OwnerDepartment = {
  departmentId: number
  key: string
  name: string
  departmentType: string
  entranceFeeTickets: number
  capacity: number
  operatingStatus: string
  description: string
  reserveBalance: number
  generatedBalance: number
  color: string
}

type DepartmentTrendPoint = {
  date: string
  label: string
  [key: string]: string | number
}

type ActivityPoint = {
  id: number
  label: string
  value: number
  type: string
  department: string
  item: string
}

type TransactionLog = {
  id: number
  type: string
  amount: number
  signedAmount: number
  departmentName: string
  employeeName: string
  itemName: string
  createdAt: string
  note: string
}

type InvestmentLog = {
  id: number
  amount: number
  createdAt: string
  createdByName: string
  note: string
}

export type OwnerDashboardProps = {
  currentUser: {
    name: string
    role: string
  }
  flash: string | null
  error: string | null
  summary: OwnerSummary
  departments: OwnerDepartment[]
  departmentTrend: DepartmentTrendPoint[]
  activityChart: ActivityPoint[]
  recentTransactions: TransactionLog[]
  investmentLogs: InvestmentLog[]
}

function labelize(value: string) {
  return value.replace(/_/g, " ").replace(/\b\w/g, (letter) => letter.toUpperCase())
}

function number(value: number) {
  return value.toLocaleString()
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

const inputClass =
  "h-10 rounded-base border-2 border-border bg-secondary-background px-3 font-sans text-sm font-base focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
const textInputClass =
  "min-h-24 rounded-base border-2 border-border bg-secondary-background px-3 py-2 font-sans text-sm font-base focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"

function DepartmentTrendTooltip({
  active,
  label,
  payload,
}: {
  active?: boolean
  label?: string | number
  payload?: Array<{
    color?: string
    dataKey?: string | number
    value?: string | number
    payload?: DepartmentTrendPoint
  }>
}) {
  const config = useChartConfig()
  const rows = payload?.filter((item) => Number(item.value ?? 0) !== 0) ?? []

  if (!active || rows.length === 0) {
    return null
  }

  return (
    <div className="max-w-sm rounded-base border-2 border-border bg-secondary-background p-3 text-sm shadow-shadow">
      <p className="m-0 mb-2 font-heading">{label}</p>
      <div className="grid gap-2">
        {rows.map((item) => {
          const key = String(item.dataKey)
          const chartItem = config[key]
          const generated = Number(item.payload?.[`${key}_generated`] ?? 0)
          const payout = Number(item.payload?.[`${key}_payout`] ?? 0)

          return (
            <div className="grid gap-1" key={key}>
              <div className="flex items-center justify-between gap-4">
                <span className="flex items-center gap-2">
                  <span
                    className="inline-block size-3 rounded-full border border-border"
                    style={{ background: item.color }}
                  />
                  {chartItem?.label ?? key}
                </span>
                <strong>{number(Number(item.value ?? 0))}</strong>
              </div>
              <p className="m-0 text-xs text-muted-foreground">
                Generated {number(generated)} / Given {number(payout)}
              </p>
            </div>
          )
        })}
      </div>
    </div>
  )
}

function ModalShell({
  children,
  onClose,
  title,
}: {
  children: React.ReactNode
  onClose: () => void
  title: string
}) {
  return (
    <div
      aria-labelledby={`${title.toLowerCase().replace(/\s+/g, "-")}-title`}
      aria-modal="true"
      className="fixed inset-0 z-50 grid place-items-center bg-overlay p-4"
      role="dialog"
    >
      <Card className="max-h-[90vh] w-full max-w-2xl overflow-y-auto">
        <CardHeader>
          <div className="flex items-start justify-between gap-4">
            <CardTitle id={`${title.toLowerCase().replace(/\s+/g, "-")}-title`}>
              {title}
            </CardTitle>
            <Button onClick={onClose} size="sm" variant="neutral">
              Close
            </Button>
          </div>
        </CardHeader>
        <CardContent>{children}</CardContent>
      </Card>
    </div>
  )
}

function DepartmentFields({
  department,
}: {
  department?: OwnerDepartment
}) {
  return (
    <>
      <Field label="Name">
        <input
          className={inputClass}
          defaultValue={department?.name ?? ""}
          name={department ? "name" : "department_name"}
          required
          type="text"
        />
      </Field>
      <Field label="Type">
        <select
          className={inputClass}
          defaultValue={department?.departmentType ?? "play_area"}
          name="department_type"
          required
        >
          <option value="play_area">Play Area</option>
          <option value="gift_shop">Gift Shop</option>
          <option value="customer_support">Customer Support</option>
        </select>
      </Field>
      <Field label="Entrance Fee">
        <input
          className={inputClass}
          defaultValue={department?.entranceFeeTickets ?? 10}
          max={100}
          min={0}
          name="entrance_fee_tickets"
          required
          type="number"
        />
      </Field>
      <Field label="Capacity">
        <input
          className={inputClass}
          defaultValue={department?.capacity ?? 10}
          min={0}
          name="capacity"
          required
          step={1}
          type="number"
        />
      </Field>
      <Field label="Status">
        <select
          className={inputClass}
          defaultValue={department?.operatingStatus ?? "active"}
          name="operating_status"
          required
        >
          <option value="active">Active</option>
          <option value="out_of_order">Out Of Order</option>
          <option value="inactive">Inactive</option>
        </select>
      </Field>
      <Field label="Description">
        <textarea
          className={textInputClass}
          defaultValue={department?.description ?? ""}
          maxLength={255}
          name="description"
        />
      </Field>
    </>
  )
}

export function OwnerDashboard({
  currentUser,
  flash,
  error,
  summary,
  departments,
  departmentTrend,
  activityChart,
  recentTransactions,
  investmentLogs,
}: OwnerDashboardProps) {
  const [isCreateOpen, setIsCreateOpen] = useState(false)
  const [isInvestmentOpen, setIsInvestmentOpen] = useState(false)
  const [editingDepartment, setEditingDepartment] = useState<OwnerDepartment | null>(null)
  const [budgetingDepartment, setBudgetingDepartment] = useState<OwnerDepartment | null>(null)
  const [budgetIncrease, setBudgetIncrease] = useState(0)
  const [showInvestmentWarning, setShowInvestmentWarning] = useState(false)

  useEffect(() => {
    document.documentElement.classList.add("ams-owner-theme")

    return () => {
      document.documentElement.classList.remove("ams-owner-theme")
    }
  }, [])

  const departmentChartConfig = useMemo<ChartConfig>(
    () =>
      Object.fromEntries(
        departments.map((department) => [
          department.key,
          {
            color: department.color,
            label: department.name,
          },
        ]),
      ),
    [departments],
  )
  const previewCredits = summary.credits - budgetIncrease
  const previewDepartmentBudget = budgetingDepartment
    ? budgetingDepartment.reserveBalance + budgetIncrease
    : 0
  const activityConfig: ChartConfig = {
    value: {
      color: "#e3337e",
      label: "Signed Tickets",
    },
  }

  return (
    <>
      <DashboardShell
        actions={
          <>
            <Button onClick={() => setIsInvestmentOpen(true)}>Increase Credits</Button>
            <a className={buttonClasses({ variant: "neutral" })} href="index.php">
              Back Home
            </a>
            <a className={buttonClasses()} href="logout.php">
              Logout
            </a>
          </>
        }
        description="Manage credits, department budgets, and ticket economy trends."
        roleLabel={`Logged in as ${currentUser.name}`}
        title="Owner Dashboard"
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

        <Section title="Ticket Summary">
          <ResponsiveGrid>
            <StatCard detail="Available owner-backed ticket credits" label="Credits" value={number(summary.credits)} />
            <StatCard detail="All non-reporting ticket balances" label="Circulation" value={number(summary.circulation)} />
            <StatCard label="Generated" value={number(summary.departmentGenerated)} />
            <StatCard detail="Tickets collected by gift shop" label="Gift Shop Revenue" value={number(summary.giftShopRevenue)} />
            <StatCard detail="Across active play sessions" label="Active Attendees" value={number(summary.activeAttendees)} />
          </ResponsiveGrid>

          <Card>
            <CardHeader>
              <CardTitle>Owner Investment Log</CardTitle>
              <CardDescription>Recent credit increases from owner investment.</CardDescription>
            </CardHeader>
            <CardContent>
              {investmentLogs.length === 0 ? (
                <p className="m-0 text-muted-foreground">No owner investments logged yet.</p>
              ) : (
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>Amount</TableHead>
                      <TableHead>Created</TableHead>
                      <TableHead>By</TableHead>
                      <TableHead>Note</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {investmentLogs.map((log) => (
                      <TableRow key={log.id}>
                        <TableCell className="font-heading">{number(log.amount)}</TableCell>
                        <TableCell>{log.createdAt}</TableCell>
                        <TableCell>{log.createdByName}</TableCell>
                        <TableCell>{log.note}</TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              )}
            </CardContent>
          </Card>
        </Section>

        <Section
          description="Net tickets are generated tickets minus tickets given out by each department."
          title="Per Week Department Summary"
        >
          <ChartContainer config={departmentChartConfig}>
            <LineChart data={departmentTrend} margin={{ bottom: 8, left: 8, right: 16, top: 16 }}>
              <CartesianGrid strokeDasharray="6 6" vertical={false} />
              <XAxis dataKey="label" tickLine={false} />
              <YAxis tickLine={false} />
              <Tooltip content={<DepartmentTrendTooltip />} />
              {departments.map((department) => (
                <Line
                  dataKey={department.key}
                  dot={{ r: 4 }}
                  key={department.key}
                  name={department.name}
                  stroke={`var(--color-${department.key})`}
                  type="monotone"
                />
              ))}
            </LineChart>
          </ChartContainer>
        </Section>

        <Section
          actions={<Button onClick={() => setIsCreateOpen(true)}>Create Department</Button>}
          title="Department Controls"
        >
          <Card>
            <CardContent>
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Name</TableHead>
                    <TableHead>Type</TableHead>
                    <TableHead>Entrance Fee</TableHead>
                    <TableHead>Capacity</TableHead>
                    <TableHead>Status</TableHead>
                    <TableHead>Budget / Prize Limit</TableHead>
                    <TableHead>Generated</TableHead>
                    <TableHead>Actions</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {departments.map((department) => (
                    <TableRow key={department.departmentId}>
                      <TableCell className="font-heading">{department.name}</TableCell>
                      <TableCell>{labelize(department.departmentType)}</TableCell>
                      <TableCell>{number(department.entranceFeeTickets)}</TableCell>
                      <TableCell>{department.departmentType === "play_area" ? number(department.capacity) : "N/A"}</TableCell>
                      <TableCell>
                        <Badge variant={department.operatingStatus === "active" ? "success" : "warning"}>
                          {labelize(department.operatingStatus)}
                        </Badge>
                      </TableCell>
                      <TableCell>
                        <div className="flex items-center justify-between gap-2">
                          <span className="font-heading">{number(department.reserveBalance)}</span>
                          <Button
                            aria-label={`Edit ${department.name} budget`}
                            onClick={() => {
                              setBudgetingDepartment(department)
                              setBudgetIncrease(0)
                            }}
                            size="sm"
                            variant="neutral"
                          >
                            <span aria-hidden="true" className="flex items-center">
                              <Plus className="size-3" />
                              <Minus className="size-3" />
                            </span>
                          </Button>
                        </div>
                      </TableCell>
                      <TableCell>{number(department.generatedBalance)}</TableCell>
                      <TableCell>
                        <Button
                          aria-label={`Edit ${department.name}`}
                          onClick={() => {
                            setEditingDepartment(department)
                          }}
                          size="sm"
                        >
                          <Pencil aria-hidden="true" className="size-4" />
                        </Button>
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </CardContent>
          </Card>
        </Section>

        <Section description="Signed raw ticket transactions from the last two weeks." title="Ticket Activity">
          <ChartContainer config={activityConfig}>
            <LineChart data={activityChart} margin={{ bottom: 8, left: 8, right: 16, top: 16 }}>
              <CartesianGrid strokeDasharray="6 6" vertical={false} />
              <XAxis dataKey="label" minTickGap={28} tickLine={false} />
              <YAxis tickLine={false} />
              <ChartTooltip content={<ChartTooltipPanel />} />
              <Line dataKey="value" dot={{ r: 4 }} stroke="var(--color-value)" type="monotone" />
            </LineChart>
          </ChartContainer>

          <Card>
            <CardHeader>
              <CardTitle>Recent Ticket Activity</CardTitle>
              <CardDescription>Latest ticket ledger entries.</CardDescription>
            </CardHeader>
            <CardContent>
              {recentTransactions.length === 0 ? (
                <p className="m-0 text-muted-foreground">No ticket transactions have been recorded yet.</p>
              ) : (
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>Type</TableHead>
                      <TableHead>Signed</TableHead>
                      <TableHead>Department</TableHead>
                      <TableHead>Employee</TableHead>
                      <TableHead>Item</TableHead>
                      <TableHead>Created</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {recentTransactions.map((transaction) => (
                      <TableRow key={transaction.id}>
                        <TableCell>{labelize(transaction.type)}</TableCell>
                        <TableCell className={transaction.signedAmount < 0 ? "font-heading text-danger" : "font-heading"}>
                          {number(transaction.signedAmount)}
                        </TableCell>
                        <TableCell>{transaction.departmentName}</TableCell>
                        <TableCell>{transaction.employeeName}</TableCell>
                        <TableCell>{transaction.itemName}</TableCell>
                        <TableCell>{transaction.createdAt}</TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              )}
            </CardContent>
          </Card>
        </Section>
      </DashboardShell>

      {isCreateOpen ? (
        <ModalShell onClose={() => setIsCreateOpen(false)} title="Create Department">
          <form action="owner_dashboard.php" className="grid gap-4" method="post">
            <input name="action" type="hidden" value="create_department" />
            <DepartmentFields />
            <Button type="submit">Create Department</Button>
          </form>
        </ModalShell>
      ) : null}

      {editingDepartment ? (
        <ModalShell onClose={() => setEditingDepartment(null)} title={`Update ${editingDepartment.name}`}>
          <form action="owner_dashboard.php" className="grid gap-4" method="post">
            <input name="action" type="hidden" value="update_department" />
            <input name="department_id" type="hidden" value={editingDepartment.departmentId} />
            <DepartmentFields department={editingDepartment} />
            <Button type="submit">Save Department Details</Button>
          </form>
        </ModalShell>
      ) : null}

      {budgetingDepartment ? (
        <ModalShell onClose={() => setBudgetingDepartment(null)} title={`Update ${budgetingDepartment.name} Budget`}>
          <Card className="bg-main">
            <CardHeader>
              <CardTitle>Budget / Prize Limit</CardTitle>
            </CardHeader>
            <CardContent>
              <form
                action="owner_dashboard.php"
                className="grid gap-4"
                method="post"
                onSubmit={(event) => {
                  if (previewCredits < 0 || previewDepartmentBudget < 0) {
                    event.preventDefault()
                    setShowInvestmentWarning(true)
                  }
                }}
              >
                <input name="action" type="hidden" value="allocate_budget" />
                <input name="department_id" type="hidden" value={budgetingDepartment.departmentId} />
                <Field label="Budget Change">
                  <input
                    className={inputClass}
                    name="amount"
                    onChange={(event) => setBudgetIncrease(Number(event.currentTarget.value || 0))}
                    step={1}
                    type="number"
                  />
                </Field>
                <div className="grid gap-1 rounded-base border-2 border-border bg-secondary-background p-3">
                  <p className="m-0">Credits now: {number(summary.credits)}</p>
                  <p className={previewCredits < 0 ? "m-0 font-heading text-danger" : "m-0 font-heading"}>
                    Credits after increase: {number(previewCredits)}
                  </p>
                  <p className="m-0">Department budget now: {number(budgetingDepartment.reserveBalance)}</p>
                  <p className={previewDepartmentBudget < 0 ? "m-0 font-heading text-danger" : "m-0 font-heading"}>
                    Department budget after change: {number(previewDepartmentBudget)}
                  </p>
                </div>
                <Button type="submit">Apply Budget Change</Button>
              </form>
            </CardContent>
          </Card>
        </ModalShell>
      ) : null}

      {isInvestmentOpen ? (
        <ModalShell onClose={() => setIsInvestmentOpen(false)} title="Increase Investment">
          <form action="owner_dashboard.php" className="grid gap-4" method="post">
            <input name="action" type="hidden" value="add_investment" />
            <Field label="Credits To Add">
              <input className={inputClass} min={1} name="amount" required step={1} type="number" />
            </Field>
            <Button type="submit">Increase Credits</Button>
          </form>
        </ModalShell>
      ) : null}

      {showInvestmentWarning ? (
        <ModalShell onClose={() => setShowInvestmentWarning(false)} title="Increase Investment First">
          <div className="grid gap-4">
            <p className="m-0">
              This budget change would make credits or the department budget negative. Increase owner investment or lower the decrease before applying it.
            </p>
            <Button
              onClick={() => {
                setShowInvestmentWarning(false)
                setIsInvestmentOpen(true)
              }}
            >
              Increase Investment
            </Button>
          </div>
        </ModalShell>
      ) : null}
    </>
  )
}
