import { useEffect, useState } from "react"

import { DashboardShell, Section } from "@/components/layout"
import {
  Badge,
  Button,
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
  buttonClasses,
} from "@/components/ui"

type AdminAccount = {
  userId: number
  name: string
  email: string
  role: string
  pendingApproval: boolean
  isCurrentUser: boolean
}

export type AdminDashboardProps = {
  currentUser: {
    name: string
    role: string
  }
  flash: string | null
  error: string | null
  pendingUsers: AdminAccount[]
  existingUsers: AdminAccount[]
}

function roleLabel(role: string) {
  return role.replace("_", " ").replace(/\b\w/g, (letter) => letter.toUpperCase())
}

function AccountActionForm({
  action,
  user,
  children,
}: {
  action: "approve" | "reject"
  user: AdminAccount
  children: React.ReactNode
}) {
  return (
    <form action="admin_dashboard.php" method="post">
      <input name="user_id" type="hidden" value={user.userId} />
      <button className={buttonClasses({ size: "sm" })} name="action" type="submit" value={action}>
        {children}
      </button>
    </form>
  )
}

export function AdminDashboard({
  currentUser,
  flash,
  error,
  pendingUsers,
  existingUsers,
}: AdminDashboardProps) {
  const [isExistingOpen, setIsExistingOpen] = useState(false)
  const [deleteTarget, setDeleteTarget] = useState<AdminAccount | null>(null)

  useEffect(() => {
    document.documentElement.classList.add("ams-admin-theme")

    return () => {
      document.documentElement.classList.remove("ams-admin-theme")
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
        description="Review account requests and manage approved staff access."
        roleLabel={`Logged in as ${currentUser.name}`}
        title="Admin Dashboard"
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

        <Section
          description="Approve staff accounts into the system or reject requests that should not proceed."
          title="Pending Accounts"
        >
          <Card>
            <CardHeader>
              <CardTitle>Approval Queue</CardTitle>
              <CardDescription>
                {pendingUsers.length === 0
                  ? "No accounts are waiting for admin approval."
                  : `${pendingUsers.length} account request(s) need review.`}
              </CardDescription>
            </CardHeader>
            <CardContent>
              {pendingUsers.length === 0 ? (
                <p className="m-0 text-muted-foreground">No accounts are pending approval.</p>
              ) : (
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>Name</TableHead>
                      <TableHead>Email</TableHead>
                      <TableHead>Role</TableHead>
                      <TableHead>Actions</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {pendingUsers.map((user) => (
                      <TableRow key={user.userId}>
                        <TableCell className="font-heading">{user.name}</TableCell>
                        <TableCell>{user.email}</TableCell>
                        <TableCell>
                          <Badge variant="accent">{roleLabel(user.role)}</Badge>
                        </TableCell>
                        <TableCell>
                          <div className="flex flex-wrap gap-2">
                            <AccountActionForm action="approve" user={user}>
                              Approve
                            </AccountActionForm>
                            <AccountActionForm action="reject" user={user}>
                              Reject
                            </AccountActionForm>
                          </div>
                        </TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              )}
            </CardContent>
          </Card>
        </Section>

        <Section title="Existing Accounts">
          <Card>
            <button
              aria-expanded={isExistingOpen}
              className="flex w-full items-center justify-between gap-4 border-0 bg-transparent p-0 text-left"
              onClick={() => setIsExistingOpen((isOpen) => !isOpen)}
              type="button"
            >
              <span>
                <CardTitle>Approved Account Directory</CardTitle>
                <CardDescription className="mt-2">
                  {existingUsers.length} approved account(s). Click to expand.
                </CardDescription>
              </span>
              <Badge variant="default">{isExistingOpen ? "Collapse" : "Expand"}</Badge>
            </button>

            {isExistingOpen ? (
              <CardContent className="mt-5">
                {existingUsers.length === 0 ? (
                  <p className="m-0 text-muted-foreground">No approved accounts yet.</p>
                ) : (
                  <Table>
                    <TableHeader>
                      <TableRow>
                        <TableHead>Name</TableHead>
                        <TableHead>Email</TableHead>
                        <TableHead>Role</TableHead>
                        <TableHead>Actions</TableHead>
                      </TableRow>
                    </TableHeader>
                    <TableBody>
                      {existingUsers.map((user) => (
                        <TableRow key={user.userId}>
                          <TableCell className="font-heading">
                            {user.name}
                            {user.isCurrentUser ? (
                              <Badge className="ml-2" variant="neutral">
                                You
                              </Badge>
                            ) : null}
                          </TableCell>
                          <TableCell>{user.email}</TableCell>
                          <TableCell>
                            <Badge variant="accent">{roleLabel(user.role)}</Badge>
                          </TableCell>
                          <TableCell>
                            <Button
                              disabled={user.isCurrentUser}
                              onClick={() => setDeleteTarget(user)}
                              size="sm"
                              variant="danger"
                            >
                              Delete
                            </Button>
                          </TableCell>
                        </TableRow>
                      ))}
                    </TableBody>
                  </Table>
                )}
              </CardContent>
            ) : null}
          </Card>
        </Section>
      </DashboardShell>

      {deleteTarget ? (
        <div
          aria-labelledby="delete-account-title"
          aria-modal="true"
          className="fixed inset-0 z-50 grid place-items-center bg-overlay p-4"
          role="dialog"
        >
          <Card className="w-full max-w-md">
            <CardHeader>
              <CardTitle id="delete-account-title">Delete Account?</CardTitle>
              <CardDescription>
                This will permanently delete {deleteTarget.name}'s account and linked employee record.
              </CardDescription>
            </CardHeader>
            <CardContent>
              <div className="flex flex-wrap gap-3">
                <form action="admin_dashboard.php" method="post">
                  <input name="user_id" type="hidden" value={deleteTarget.userId} />
                  <button
                    className={buttonClasses({ variant: "danger" })}
                    name="action"
                    type="submit"
                    value="delete_existing"
                  >
                    Yes, Delete
                  </button>
                </form>
                <Button onClick={() => setDeleteTarget(null)} variant="neutral">
                  Cancel
                </Button>
              </div>
            </CardContent>
          </Card>
        </div>
      ) : null}
    </>
  )
}
