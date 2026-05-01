// hr dashboard: sick approvals, employee directory + edit modals, weekly hour charts for play-area staff, audit log.
import { useEffect, useState } from "react"
import { Pencil } from "lucide-react"
import { Bar, BarChart, CartesianGrid, Tooltip, XAxis, YAxis } from "recharts"

import { DashboardShell, Section } from "@/components/layout"
import {
  Badge,
  Button,
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
  ChartContainer,
  ChartTooltipPanel,
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
  buttonClasses,
  type ChartConfig,
} from "@/components/ui"

// minimal department row for assignment dropdowns
type Department = {
  departmentId: number
  name: string
}

type WeeklyHour = {
  date: string
  label: string
  hours: number
}

type RecentShift = {
  shiftId: number
  startTime: string
  endTime: string
}

type Employee = {
  employeeId: number
  name: string
  email: string
  role: string
  departmentId: number | null
  departmentType: string
  departmentName: string
  hourlyWage: number
  status: string
  weeklyHours: WeeklyHour[]
  totalWeekHours: number
  recentShifts: RecentShift[]
}

type HrLog = {
  logId: number
  actionType: string
  employeeName: string
  handledByName: string
  details: string
  createdAt: string
}

type SickRequest = {
  sickRequestId: number
  employeeId: number
  employeeName: string
  requestDate: string
  status: string
  notes: string
  requestedAt: string
  reviewedAt: string
  reviewerName: string
  reviewNotes: string
}

// embedded json from hr_dashboard.php bootstrap
export type HrDashboardProps = {
  currentUser: {
    name: string
    role: string
  }
  flash: string | null
  error: string | null
  departments: Department[]
  employees: Employee[]
  logs: HrLog[]
  sickRequests: SickRequest[]
}

const inputClass =
  "h-10 rounded-base border-2 border-border bg-secondary-background px-3 font-sans text-sm font-base focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"

// title-case enums and status strings for tables
function labelize(value: string) {
  return value.replace(/_/g, " ").replace(/\b\w/g, (letter) => letter.toUpperCase())
}

function money(value: number) {
  return `$${value.toFixed(2)}`
}

// shared labeled control used across hr forms
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

// accessible modal wrapper for add/edit/terminate flows
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

// department `<select>` shared by add employee + edit employee forms
function DepartmentSelect({
  departments,
  value,
}: {
  departments: Department[]
  value?: number | null
}) {
  return (
    <select className={inputClass} defaultValue={value ?? ""} name="department_id" required>
      <option disabled value="">
        Select department
      </option>
      {departments.map((department) => (
        <option key={department.departmentId} value={department.departmentId}>
          {department.name}
        </option>
      ))}
    </select>
  )
}

// series styling for weekly hour bar charts (single hours series per chart)
const chartConfig: ChartConfig = {
  hours: {
    color: "#ebd22f",
    label: "Hours",
  },
}

export function HrDashboard({
  currentUser,
  flash,
  error,
  departments,
  employees,
  logs,
  sickRequests,
}: HrDashboardProps) {
  const [isAddOpen, setIsAddOpen] = useState(false)
  const [editingEmployee, setEditingEmployee] = useState<Employee | null>(null)
  const [terminatingEmployee, setTerminatingEmployee] = useState<Employee | null>(null)
  // weekly charts only include employees tied to play-area departments (has hour breakdown data)
  const playAreaEmployees = employees.filter((employee) => employee.departmentType === "play_area")

  // hr chrome variables only while mounted
  useEffect(() => {
    document.documentElement.classList.add("ams-hr-theme")

    return () => {
      document.documentElement.classList.remove("ams-hr-theme")
    }
  }, [])

  return (
    <>
      <DashboardShell
        actions={
          <>
            <a className={buttonClasses({ variant: "neutral" })} href="index.php">
              Back Home
            </a>
            <a className={buttonClasses()} href="logout.php">
              Logout
            </a>
          </>
        }
        description="Manage employees, departments, wages, status changes, and shift coverage."
        roleLabel={`Logged in as ${currentUser.name}`}
        title="HR Dashboard"
      >
        {/* flash / error from php redirects */}
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

        {/* hr approves or denies sick-day requests (posts review_sick_request) */}
        <Section
          description="Approve or deny employee sick-day requests. Approved days add 8 paid hours to the employee's weekly total."
          title="Sick Requests"
        >
          <Card>
            <CardContent>
              {sickRequests.length === 0 ? (
                <p className="m-0 text-muted-foreground">No sick requests submitted yet.</p>
              ) : (
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>Employee</TableHead>
                      <TableHead>Date</TableHead>
                      <TableHead>Status</TableHead>
                      <TableHead>Notes</TableHead>
                      <TableHead>Actions</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {sickRequests.map((request) => (
                      <TableRow key={request.sickRequestId}>
                        <TableCell className="font-heading">{request.employeeName}</TableCell>
                        <TableCell>{request.requestDate}</TableCell>
                        <TableCell>
                          <Badge variant={request.status === "approved" ? "success" : request.status === "denied" ? "danger" : "warning"}>
                            {labelize(request.status)}
                          </Badge>
                        </TableCell>
                        <TableCell>{request.notes || request.reviewNotes}</TableCell>
                        <TableCell>
                          {request.status === "waiting" ? (
                            <div className="flex flex-wrap gap-2">
                              <form action="hr_dashboard.php" method="post">
                                <input name="action" type="hidden" value="review_sick_request" />
                                <input name="sick_request_id" type="hidden" value={request.sickRequestId} />
                                <button className={buttonClasses({ size: "sm" })} name="status" type="submit" value="approved">
                                  Approve
                                </button>
                              </form>
                              <form action="hr_dashboard.php" method="post">
                                <input name="action" type="hidden" value="review_sick_request" />
                                <input name="sick_request_id" type="hidden" value={request.sickRequestId} />
                                <button className={buttonClasses({ size: "sm", variant: "danger" })} name="status" type="submit" value="denied">
                                  Deny
                                </button>
                              </form>
                            </div>
                          ) : (
                            request.reviewerName || "Reviewed"
                          )}
                        </TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              )}
            </CardContent>
          </Card>
        </Section>

        {/* full roster; edit opens modal with shift tools */}
        <Section
          actions={<Button onClick={() => setIsAddOpen(true)}>Add New Employee</Button>}
          title="Employees"
        >
          <Card>
            <CardContent>
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Name</TableHead>
                    <TableHead>Email</TableHead>
                    <TableHead>Role</TableHead>
                    <TableHead>Department</TableHead>
                    <TableHead>Wage</TableHead>
                    <TableHead>Status</TableHead>
                    <TableHead>Actions</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {employees.map((employee) => (
                    <TableRow key={employee.employeeId}>
                      <TableCell className="font-heading">{employee.name}</TableCell>
                      <TableCell>{employee.email}</TableCell>
                      <TableCell>{labelize(employee.role)}</TableCell>
                      <TableCell>{employee.departmentName}</TableCell>
                      <TableCell>{money(employee.hourlyWage)}</TableCell>
                      <TableCell>
                        <Badge variant={employee.status === "terminated" ? "danger" : "success"}>
                          {labelize(employee.status)}
                        </Badge>
                      </TableCell>
                      <TableCell>
                        <Button
                          aria-label={`Edit ${employee.name}`}
                          onClick={() => setEditingEmployee(employee)}
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

        {/* bar chart per play-area employee — data scoped to current week from server */}
        <Section
          description="Weekly hours are grouped by employee, with two charts per row on wider screens."
          title="Weekly Shift View"
        >
          <div className="grid gap-5 xl:grid-cols-2">
            {playAreaEmployees.map((employee) => (
              <Card key={employee.employeeId}>
                <CardHeader>
                  <div className="flex items-start justify-between gap-4">
                    <div>
                      <CardTitle>{employee.name}</CardTitle>
                      <CardDescription>{employee.departmentName}</CardDescription>
                    </div>
                    <Badge variant="default">{employee.totalWeekHours.toFixed(2)} hrs/week</Badge>
                  </div>
                </CardHeader>
                <CardContent>
                  <ChartContainer className="min-h-64" config={chartConfig}>
                    <BarChart data={employee.weeklyHours} margin={{ bottom: 8, left: 8, right: 16, top: 16 }}>
                      <CartesianGrid strokeDasharray="6 6" vertical={false} />
                      <XAxis dataKey="label" tickLine={false} />
                      <YAxis tickLine={false} />
                      <Tooltip content={<ChartTooltipPanel />} />
                      <Bar dataKey="hours" fill="var(--color-hours)" radius={5} />
                    </BarChart>
                  </ChartContainer>
                </CardContent>
              </Card>
            ))}
            {playAreaEmployees.length === 0 ? (
              <Card>
                <CardTitle>No play-area employees assigned.</CardTitle>
                <CardDescription>Weekly shift charts only show employees assigned to play-area departments.</CardDescription>
              </Card>
            ) : null}
          </div>
        </Section>

        {/* immutable audit trail from hr_action_logs */}
        <Section title="HR Action Log">
          <Card>
            <CardContent>
              {logs.length === 0 ? (
                <p className="m-0 text-muted-foreground">No HR actions have been logged yet.</p>
              ) : (
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>Action</TableHead>
                      <TableHead>Employee</TableHead>
                      <TableHead>Handled By</TableHead>
                      <TableHead>Created</TableHead>
                      <TableHead>Details</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {logs.map((log) => (
                      <TableRow key={log.logId}>
                        <TableCell className="font-heading">{labelize(log.actionType)}</TableCell>
                        <TableCell>{log.employeeName}</TableCell>
                        <TableCell>{log.handledByName}</TableCell>
                        <TableCell>{log.createdAt}</TableCell>
                        <TableCell>{log.details}</TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              )}
            </CardContent>
          </Card>
        </Section>
      </DashboardShell>

      {/* create employee — posts add_employee */}
      {isAddOpen ? (
        <ModalShell onClose={() => setIsAddOpen(false)} title="Add New Employee">
          <form action="hr_dashboard.php" className="grid gap-4" method="post">
            <input name="action" type="hidden" value="add_employee" />
            <Field label="Name">
              <input className={inputClass} name="name" required type="text" />
            </Field>
            <Field label="Email (must match user)">
              <input className={inputClass} name="email" required type="email" />
            </Field>
            <Field label="Department">
              <DepartmentSelect departments={departments} />
            </Field>
            <Field label="Wage">
              <input className={inputClass} defaultValue="15.00" min={0} name="hourly_wage" required step="0.01" type="number" />
            </Field>
            <Button type="submit">Add Employee</Button>
          </form>
        </ModalShell>
      ) : null}

      {/* edit employee, add shift, recent shifts; terminate opens second modal */}
      {editingEmployee ? (
        <ModalShell onClose={() => setEditingEmployee(null)} title={`Edit ${editingEmployee.name}`}>
          <div className="grid gap-6">
            <form action="hr_dashboard.php" className="grid gap-4" method="post">
              <input name="action" type="hidden" value="update_employee" />
              <input name="employee_id" type="hidden" value={editingEmployee.employeeId} />
              <Field label="Name">
                <input className={inputClass} defaultValue={editingEmployee.name} name="name" required type="text" />
              </Field>
              <Field label="Department">
                <DepartmentSelect departments={departments} value={editingEmployee.departmentId} />
              </Field>
              <Field label="Wage">
                <input
                  className={inputClass}
                  defaultValue={editingEmployee.hourlyWage.toFixed(2)}
                  min={0}
                  name="hourly_wage"
                  required
                  step="0.01"
                  type="number"
                />
              </Field>
              <Field label="Status">
                <select className={inputClass} defaultValue={editingEmployee.status} name="status">
                  <option value="active">Active</option>
                  <option value="transferred">Transferred</option>
                  <option value="terminated">Terminated</option>
                </select>
              </Field>
              <Button type="submit">Save Employee</Button>
            </form>

            <Card className="bg-main">
              <CardHeader>
                <CardTitle>Add Shift</CardTitle>
                <CardDescription className="text-main-foreground">
                  Add a shift directly from the employee edit popup.
                </CardDescription>
              </CardHeader>
              <CardContent>
                <form action="hr_dashboard.php" className="grid gap-4" method="post">
                  <input name="action" type="hidden" value="add_shift" />
                  <input name="employee_id" type="hidden" value={editingEmployee.employeeId} />
                  <Field label="Start Time">
                    <input className={inputClass} name="start_time" required type="datetime-local" />
                  </Field>
                  <Field label="End Time">
                    <input className={inputClass} name="end_time" required type="datetime-local" />
                  </Field>
                  <Button type="submit">Add Shift</Button>
                </form>
              </CardContent>
            </Card>

            <Card>
              <CardHeader>
                <CardTitle>Recent Shifts</CardTitle>
              </CardHeader>
              <CardContent>
                {editingEmployee.recentShifts.length === 0 ? (
                  <p className="m-0 text-muted-foreground">No shifts logged yet.</p>
                ) : (
                  <Table>
                    <TableHeader>
                      <TableRow>
                        <TableHead>Start</TableHead>
                        <TableHead>End</TableHead>
                      </TableRow>
                    </TableHeader>
                    <TableBody>
                      {editingEmployee.recentShifts.map((shift) => (
                        <TableRow key={shift.shiftId}>
                          <TableCell>{shift.startTime}</TableCell>
                          <TableCell>{shift.endTime}</TableCell>
                        </TableRow>
                      ))}
                    </TableBody>
                  </Table>
                )}
              </CardContent>
            </Card>

            {editingEmployee.status !== "terminated" ? (
              <Button
                onClick={() => {
                  setTerminatingEmployee(editingEmployee)
                  setEditingEmployee(null)
                }}
                variant="danger"
              >
                Terminate Employee
              </Button>
            ) : null}
          </div>
        </ModalShell>
      ) : null}

      {/* confirm terminate — posts terminate_employee */}
      {terminatingEmployee ? (
        <ModalShell onClose={() => setTerminatingEmployee(null)} title="Confirm Termination">
          <div className="grid gap-4">
            <p className="m-0">
              Terminate <strong>{terminatingEmployee.name}</strong>? This will mark the employee as terminated.
            </p>
            <div className="flex flex-wrap gap-3">
              <form action="hr_dashboard.php" method="post">
                <input name="action" type="hidden" value="terminate_employee" />
                <input name="employee_id" type="hidden" value={terminatingEmployee.employeeId} />
                <button className={buttonClasses({ variant: "danger" })} type="submit">
                  Yes, Terminate
                </button>
              </form>
              <Button onClick={() => setTerminatingEmployee(null)} variant="neutral">
                Cancel
              </Button>
            </div>
          </div>
        </ModalShell>
      ) : null}
    </>
  )
}
